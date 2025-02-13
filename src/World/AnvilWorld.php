<?php

namespace Aternos\Thanos\World;

use Exception;
use Aternos\Thanos\Chunk\AnvilChunk;
use Aternos\Thanos\Helper;
use Aternos\Thanos\RegionDirectory\AnvilRegionDirectory;

/**
 * Class AnvilWorld
 * Object representing a Minecraft Anvil world
 *
 * @package Aternos\Thanos\World
 */
class AnvilWorld implements WorldInterface
{

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string;
     */
    protected $dest;

    /**
     * @var string[]
     */
    protected $files;

    /**
     * @var AnvilRegionDirectory[]
     */
    protected $regionDirectories;

    /**
     * @var string[]
     */
    protected $otherFiles;

    /**
     * @var int
     */
    protected $regionDirectoryPointer = 0;

    /**
     * @var int
     */
    protected $chunkPointer = 0;

    /**
     * AnvilWorld constructor.
     * @param string $path
     * @param string $dest
     */
    public function __construct(string $path, string $dest)
    {
        $this->path = $path;
        $this->dest = $dest;
        $this->files = scandir($path);
        $this->findRegionDirectories();
    }

    /**
     * Get world directory path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Find the region directories of all dimensions
     *
     * @return string[]
     */
    protected function findRegionDirectories(): array
    {
        $this->regionDirectories = [];
        foreach ($this->files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (!is_dir("$this->path/$file")) {
                $this->otherFiles[] = $file;
                continue;
            }
            if ($file === 'region' && AnvilRegionDirectory::isRegionDirectory("$this->path/$file")) {
                $this->regionDirectories[] = new AnvilRegionDirectory("$this->path/$file", "$this->dest/$file");
                continue;
            }
            if (file_exists("$this->path/$file/region") &&
                AnvilRegionDirectory::isRegionDirectory("$this->path/$file/region")) {
                $this->regionDirectories[] = new AnvilRegionDirectory("$this->path/$file/region",
                    "$this->dest/$file/region");
                $other = scandir("$this->path/$file");
                foreach ($other as $o) {
                    if (!in_array($o, ['.', '..', 'region'])) {
                        $this->otherFiles[] = "$file/$o";
                    }
                }
                continue;
            }
            $this->otherFiles[] = $file;
        }
        return $this->regionDirectories;
    }

    /**
     * Get all region directories from all dimensions of this world
     *
     * @return AnvilRegionDirectory[]
     */
    public function getRegionDirectories(): array
    {
        return $this->regionDirectories;
    }

    /**
     * Check if a directory is a world directory
     *
     * @param string $path
     * @return bool
     */
    public static function isWorld(string $path): bool
    {
        $files = scandir($path);
        return (in_array('level.dat', $files) && in_array('region', $files));
    }

    /**
     * Get all files, that are not region directories
     *
     * @return string[]
     */
    public function getOtherFiles(): array
    {
        return $this->otherFiles;
    }

    /**
     * Copy all other files to $dest
     *
     */
    public function copyOtherFiles(): void
    {
        @mkdir($this->dest);
        foreach ($this->otherFiles as $file) {
            if (is_dir("$this->path/$file")) {
                Helper::copyDirectory("$this->path/$file", "$this->dest/$file");
            } else {
                $parts = explode('/', $file);
                if (count($parts) > 1) {
                    array_pop($parts);
                    $dir = implode('/', $parts);
                    if (!is_dir("$this->dest/$dir")) {
                        mkdir("$this->dest/$dir", 0777, true);
                    }
                }
                copy("$this->path/$file", "$this->dest/$file");
            }
        }
        foreach ($this->regionDirectories as $dir) {
            $dir->copyOtherFiles();
        }
    }

    /**
     * Get destination
     *
     * @return string
     */
    public function getDestination(): string
    {
        return $this->dest;
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return AnvilChunk
     * @since 5.0.0
     */
    public function current()
    {
        if (!isset($this->regionDirectories[$this->regionDirectoryPointer])) {
            return null;
        }
        return ($this->regionDirectories[$this->regionDirectoryPointer]->valid() ?
            $this->regionDirectories[$this->regionDirectoryPointer]->current() : null);
    }

    /**
     * Move forward to next element
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     * @throws Exception
     */
    public function next()
    {
        if (!isset($this->regionDirectories[$this->regionDirectoryPointer])) {
            return;
        }
        $this->regionDirectories[$this->regionDirectoryPointer]->next();
        $this->chunkPointer++;
        if (!$this->regionDirectories[$this->regionDirectoryPointer]->valid()) {
            $this->regionDirectories[$this->regionDirectoryPointer]->saveAll();
            $this->regionDirectoryPointer++;
        }
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->chunkPointer;
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return (isset($this->regionDirectories[$this->regionDirectoryPointer]) &&
            $this->regionDirectories[$this->regionDirectoryPointer]->valid());
    }

    /**
     * Rewind the Iterator to the first element
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        if (isset($this->regionDirectories[$this->regionDirectoryPointer])) {
            $this->regionDirectories[$this->regionDirectoryPointer]->saveAll();
        }
        $this->regionDirectoryPointer = 0;
        $this->chunkPointer = 0;
    }
}
