<?php
namespace wiggum\services\storage\adapters;

use \wiggum\services\storage\StorageAdapter;
use \League\Flysystem\Filesystem;
use \League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use \Aws\S3\S3Client;

class AwsS3Adapter extends StorageAdapter {

    /**
     *
     * @param array $config
     * @return Filesystem
     */
    public function getFilesystem(array $config): Filesystem
    {
        $client = new S3Client($config);

        // The internal adapter
        $adapter = new AwsS3V3Adapter(
            // S3Client
            $client,
            // Bucket name
            $config['bucket']
        );

        // The FilesystemOperator
        return new Filesystem($adapter, ['visibility' => $config['visibility']]);
    }

}
