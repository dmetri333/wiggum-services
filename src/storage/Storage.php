<?php
namespace wiggum\services\storage;

use wiggum\foundation\Application;
use wiggum\services\storage\exceptions\StorageNotFoundException;

class Storage {

    private $app;
    private $disks;
    private $filesystem;

	/**
     *
     * @param Application $app
     * @param array $config
     */
    public function __construct(Application $app, array $config)
	{
        $this->app = $app;
        $this->disks = $config['disks'];
    }

    /**
     *
     * @param string $disk
     * @return Storage
     */
    public function disk(string $disk): Storage
    {
        if (!isset($this->disks[$disk])) {
            throw new StorageNotFoundException('Storage Disk Not Found: ' . $disk); 
        }

        $adapter = $this->disks[$disk]['adapter'];
        if (!class_exists($this->disks[$disk]['adapter'])) {
            throw new StorageNotFoundException('Storage Adapter Not Found: ' . $this->disks[$disk]['adapter']); 
        }

        $filesystemAdapter = $adapter($this->app);
        $this->filesystem = $filesystemAdapter->getFilesystem($this->disks[$disk]);

        return $this;
    }

    /**
     *
     * @param string $path
     * @return boolean
     */
    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    /**
     *
     * @param string $path
     * @return string
     */
    public function read(string $path): string
    {
        return $this->filesystem->read($path);
    }

    /**
     *
     * @param string $path
     * @return mixed
     */
    public function readStream(string $path)
    {
        return $this->filesystem->readStream($path);
    }

    /**
     *
     * @param string $path
     * @param boolean $recursive
     * @return mixed
     */
    public function listContents(string $path, bool $recursive = false)
    {
        return $this->filesystem->listContents($path, $recursive);
    }

    /**
     *
     * @param string $path
     * @return integer
     */
    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified($path);
    }

    /**
     *
     * @param string $path
     * @return integer
     */
    public function fileSize(string $path): int
    {
        return $this->filesystem->fileSize($path);
    }

    /**
     *
     * @param string $path
     * @return string
     */
    public function mimeType(string $path): string
    {
        return $this->filesystem->mimeType($path);
    }

    /**
     *
     * @param string $path
     * @return string
     */
    public function visibility(string $path): string
    {
        return $this->filesystem->visibility($path);
    }

    /**
     *
     * @param string $path
     * @param string $contents
     * @param array $config
     * @return void
     */
    public function write(string $path, string $contents, array $config = []): void
    {
        $this->filesystem->write($path, $contents, $config);
    }

    /**
     *
     * @param string $path
     * @param [type] $stream
     * @param array $config
     * @return void
     */
    public function writeStream(string $path, $stream, array $config = []): void
    {
        $this->filesystem->writeStream($path, $stream, $config);
    }

    /**
     *
     * @param string $path
     * @param string $visibility
     * @return void
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->filesystem->setVisibility($path, $visibility);
    }

    /**
     *
     * @param string $path
     * @return void
     */
    public function delete(string $path): void
    {
        $this->filesystem->delete($path);
    }

    /**
     *
     * @param string $path
     * @return void
     */
    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDirectory($path);
    }

    /**
     *
     * @param string $path
     * @param array $config
     * @return void
     */
    public function createDirectory(string $path, array $config = []): void
    {
        $this->filesystem->createDirectory($path, $config);
    }

    /**
     *
     * @param string $source
     * @param string $destination
     * @param array $config
     * @return void
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        $this->filesystem->move($source, $destination, $config);
    }

    /**
     *
     * @param string $source
     * @param string $destination
     * @param array $config
     * @return void
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->filesystem->copy($source, $destination, $config);
    }

}