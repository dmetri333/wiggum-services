<?php
namespace wiggum\services\storage\adapter;

use \wiggum\services\storage\StorageAdapter;
use \League\Flysystem\Filesystem;
use \League\Flysystem\Local\LocalFilesystemAdapter;

class LocalAdapter extends StorageAdapter {

    /**
     *
     * @param array $config
     * @return Filesystem
     */
    public function getFilesystem(array $config): Filesystem
    {
        $adapter = new LocalFilesystemAdapter(
            // Determine root directory
            $this->app->basePath . $config['root']
        );
        
        // The FilesystemOperator
        return new Filesystem($adapter);
    }

}