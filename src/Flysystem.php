<?php

namespace Bermuda\Flysystem;

use finfo;
use Bermuda\Clock\Clock;
use Carbon\CarbonInterface;
use BadMethodCallException;
use function Bermuda\String\{str_starts_with, str_slice, str_before, str_contains};
use Bermuda\Detector\{Detector, FinfoDetector, MimeTypeDetector};
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
 * @property-read FilesystemOperator $operator
 * @property-read Detector $detector
 */
final class Flysystem
{
    public function __construct(private ?FilesystemOperator     $operator = null,
                                private ?Detector     $detector = null
    )
    {
        $this->operator = $operator ?? OperatorFactory::makeLocal();
        $this->detector = $detector ?? new FinfoDetector();
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->operator, $name)) {
            return call_user_func_array([$this->operator, $name], $arguments);
        }

        if (str_starts_with($name, 'is')) {
            return $this->isFile($arguments[0], str_slice($name, 2));
        }

        throw new BadMethodCallException(
            sprintf('Method %s doesnt exists from %s', $name, __CLASS__)
        );
    }
    
    public function __get(string $name)
    {
        return match ($name) {
            'operator' => $this->operator,
            'detector' => $this->detector
        };
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
            && str_contains($this->mimeType($location), $type);
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
     * @return int|CarbonInterface
     */
    public function lastModified(string $location, bool $asCarbon = true): int|CarbonInterface
    {
        $stamp = $this->operator->lastModified($location);
        return $asCarbon ? Clock::create($stamp) : $stamp ;
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
        return $this->detector->detectExtension($content);
    }

    /**
     * @param string $filename
     * @param string $content
     * @return File|Directory
     * @throws FilesystemException
     */
    public function create(string $filename, ?string $content = null): File|Directory
    {
        return $content !== null ? File::create($filename, $content, $this)
            : Directory::create($filename, $this);
    }

    /**
     * @param string $filename
     * @param string $content
     * @return File
     * @throws FilesystemException
     */
    public function createFile(string $filename, string $content = ''): File
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
