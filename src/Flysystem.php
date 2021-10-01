<?php

namespace Bermuda\Flysystem;

use finfo;
use Carbon\Carbon;
use BadMethodCallException;
use Bermuda\String\Stringy;
use Nyholm\Psr7\Factory\Psr17Factory;
use Bermuda\Detector\{ExtensionDetector, FinfoDetector};
use Psr\Http\Message\StreamFactoryInterface;
use League\Flysystem\{
    DirectoryAttributes,
    DirectoryListing,
    FileAttributes,
    FilesystemOperator,
    FilesystemException,
    StorageAttributes
};

/**
 * @mixin FilesystemOperator
 * @mixin StreamFactoryInterface
 */
final class Flysystem
{
    public function __construct(private ?FilesystemOperator     $operator = null,
                                private ?StreamFactoryInterface $streamFactory = null,
                                private ?ExtensionDetector      $detector = null
    )
    {
        $this->operator = $operator ?? OperatorFactory::makeLocal();
        $this->streamFactory = $this->streamFactory ?? new Psr17Factory();
        $this->detector = $detector ?? new FinfoDetector();
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->operator, $name)) {
            return call_user_func_array([$this->operator, $name], $arguments);
        }

        if (method_exists($this->streamFactory, $name)) {
            return call_user_func_array([$this->streamFactory, $name], $arguments);
        }
        
        if (($stringy = new Stringy($name))->start(2)->equals('is')) {
            return $this->isFile($arguments[0], $stringy->slice(2));
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
        if ($this->isDirectory($location)) {
            return $this->doFileSize(new DirectoryAttributes($location));
        }

        return $this->operator->fileSize($location);
    }

    private function doFileSize(StorageAttributes $attributes): int
    {
        if ($attributes->isFile()) {
            return $attributes instanceof FileAttributes ? $attributes->fileSize()
                : $this->operator->fileSize($attributes->path());
        }

        $size = 0;
        foreach ($this->operator->listContents($attributes->path()) as $content) {
            $size += $this->doFileSize($content);
        }

        return $size;
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
     * @throws NoSuchFile
     */
    public function open(string $location): File|Directory
    {
        try {
            return File::open($location);
        } catch (NoSuchFile) {
            return Directory::open($location);
        }
    }

    /**
     * @param string $filename
     * @return string
     */
    public function fileExtension(string $filename): string
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
     * @param string $content
     * @return File|Directory
     * @throws FilesystemException
     */
    public function create(string $filename, ?string $content = null): File
    {
        return $content !== null ? File::create($filename, $content, $this)
            : Directory::create($filename, $this);
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
            $file = $this->open($listContent->path());
            if ($filter === null || true === $filter($file)) {
                $list[] = $file;
            }
        }

        return $list;
    }

    /**
     * @param string $location
     * @return bool
     * @throws FilesystemException
     */
    public function exists(string $location): bool
    {
        return $this->operator->fileExists($location)
            || $this->isDirectory($location);
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
