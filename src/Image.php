<?php

namespace Bermuda\Files;

final class Image extends File
{
    private ?int $width = null;
    private ?int $height = null;

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    public function getWidth(): int
    {
        if ($this->width == null)
        {
            $this->setImageWidthAndImageHeight();
        }

        return $this->width;
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    public function getHeight(): int
    {
        if ($this->height == null)
        {
            $this->setImageWidthAndImageHeight();
        }

        return $this->height;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['width' => $this->width, 'height' => $this->height]);
    }

    /**
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    private function setImageWidthAndImageHeight(): void
    {
        $result = getimagesizefromstring($this->getContents());
        $this->width = $result[0]; $this->height = $result[1];
    }
}
