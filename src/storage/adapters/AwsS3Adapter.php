<?php
namespace wiggum\services\storage\adapter;

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
        $options = [
            'endpoint' => $config['endpoint'],
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ],
            'version' => $config['version']
        ];

        $client = new S3Client($options);

        // The internal adapter
        $adapter = new AwsS3V3Adapter(
            // S3Client
            $client,
            // Bucket name
            $config['bucket']
        );

        // The FilesystemOperator
        return new Filesystem($adapter);
    }

}