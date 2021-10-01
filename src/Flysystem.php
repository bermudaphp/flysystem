<?php

namespace Bermuda\Flysystem;

use finfo;
use Carbon\Carbon;
use BadMethodCallException;
use Bermuda\String\{Str, Stringy};
use Nyholm\Psr7\Factory\Psr17Factory;
use Bermuda\Detector\{ExtensionDetector, FinfoDetector};
use Psr\Http\Message\StreamFactoryInterface;
use League\Flysystem\{FilesystemOperator, FilesystemException};

/**
 * @mixin FilesystemOperator
 * @mixin StreamFactoryInterface
 */
final class Flysystem
{
    use Iterate;
    public function __construct(private ?FilesystemOperator     $operator = null,
                                private ?StreamFactoryInterface $streamFactory = null,
                                private ?ExtensionDetector      $detector = null
    )
    {
        $this->operator = $operator ?? OperatorFactory::makeSystem();
        $this->streamFactory = $this->streamFactory ?? new Psr17Factory();
        $this->detector = $detector ?? new FinfoDetector();
    }

    /**
     * @return self
     */
    public static function fromLocal(): self
    {
        return new Flysystem;
    }

    public function __call(string $name, array $arguments)
    {
        if (($stringy = new Stringy($name))->start(2)->equals('is')) {
            return $this->isFile($arguments[0], $stringy->slice(2));
        }

        if (method_exists($this->operator, $name)) {
            return call_user_func_array([$this->operator, $name], $arguments);
        }

        if (method_exists($this->streamFactory, $name)) {
            return call_user_func_array([$this->streamFactory, $name], $arguments);
        }

        throw new BadMethodCallException(
            sprintf('Method %s doesnt exists from %s', $name, __CLASS__)
        );
    }

    /**
     * @param string $location
     * @return bool
     * @throws FilesystemException
     */
    public function isFile(string $location, ?string $type = null): bool
    {
        if ($type === null) {
            return $this->operator->fileExists($location);
        }

        return $this->operator->fileExists($location) 
            && str_contains($this->mimeType($filename), $type);
    }

    /**
     * @param string $location
     * @return int
     * @throws FilesystemException
     */
    public function fileSize(string $location = '/'): int
    {
        if ($this->isDirectory($location))
        {
            return $this->openDirectory($location)->getSize();
        }

        return $this->operator->fileSize($location);
    }

    /**
     * @param string $location
     * @return string
     */
    public function mimeType(string $location): string
    {
        return $this->operator->mimeType($location);
    }

    /**
     * @return FilesystemOperator
     */
    public function getOperator(): FilesystemOperator
    {
        return $this->operator;
    }

    /**
     * @return float|null
     */
    public function diskTotalSpace():? float
    {
        return ($space = @disk_total_space(getcwd())) !== false ? $space : null;
    }
    
    /**
     * @return float|null
     */
    public function diskUsedSpace():? float
    {
        if (($space = $this->diskTotalSpace()) === null) {
            return null;
        }
        
        return $space - $this->diskFreeSpace();
    }

    /**
     * @return float|null
     */
    public function diskFreeSpace():? float
    {
        return ($space = @disk_free_space(getcwd())) !== false ? $space : null;
    }

    /**
     * @param string $location
     * @param bool $asCarbon
     * @return int|Carbon
     */
    public function lastModified(string $location, bool $asCarbon = true): int|Carbon
    {
        return $this->open($location)->lastModified($asCarbon);
    }

    /**
     * @param string $location
     * @return File|Directory|null
     * @throws FilesystemException
     * @throws Exceptions\NoSuchFile|Exceptions\NoSuchDirectory
     */
    public function open(string $location): File|Directory|null
    {
        try {
            return $this->openFile($location);
        } catch (Exceptions\NoSuchFile $thr) {
            try {
                return $this->openDirectory($location);
            } catch (Exceptions\NoSuchDirectory $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @return File
     * @throws FilesystemException
     * @throws Exceptions\NoSuchFile
     */
    public function openFile(string $filename): File
    {
        return File::open($filename, $this);
    }

    /**
     * @param string $location
     * @return Directory
     * @throws Exceptions\NoSuchDirectory
     */
    public function openDirectory(string $location): Directory
    {
        return Directory::open($location, $this);
    }

    /**
     * @param string $location
     * @return File[]
     * @throws FilesystemException
     */
    public function getFiles(string $location): array
    {
        return $this->openDirectory($location)->getFiles();
    }

    /**
     * @param string $location
     * @return array
     * @throws FilesystemException
     */
    public function getDirectories(string $location): array
    {
        return $this->openDirectory($location)->getChildes();
    }

    /**
     * @return StreamFactoryInterface
     */
    public function getStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * @param string $filename
     * @return string
     */
    public function extension(string $filename): string
    {
        $content = $this->operator->read($filename);
        $result = $this->detactor->detectExtension($content);

        if (str_contains($result, '/')) {
            return (new Stringy($result))->before('/');
        }

        return $result;
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function isImage(string $filename): bool
    {
        return $this->isFile($filename, 'image');
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @return File
     * @throws FilesystemException
     */
    public function createFile(?string $filename = null, string $content = ''): File
    {
        return File::create($filename, $content, $this);
    }

    /**
     * @param string $location
     * @return Directory
     * @throws FilesystemException
     */
    public function createDirectory(string $location): Directory
    {
        return Directory::create($location, $this);
    }

    /**
     * @param string $location
     * @param callable|null $filter
     * @return array<File|Directory>
     * @throws FilesystemException
     */
    public function listContents(string $location = '/', callable $filter = null): array
    {
        $list = [];

        foreach ($this->operator->listContents($location) as $listContent) {
            $flysystemData = $this->open($listContent->path());

            if ($filter === null || $filter($flysystemData)) {
                $list[] = $flysystemData;
            }
        }

        return $list;
    }

    /**
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function exists(string $path): bool
    {
        return $this->operator->fileExists($path)
            || $this->isDirectory($path);
    }

    /**
     * @param string $location
     * @return bool
     */
    public function isDirectory(string $location): bool
    {
        try {
            return strtolower($this->operator->mimeType($location)) === 'directory';
        } catch (FilesystemException $e) {
            return false;
        }
    }
}
