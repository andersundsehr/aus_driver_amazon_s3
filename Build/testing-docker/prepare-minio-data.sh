#!/bin/sh
#prepare the minio-data directory that is being used as test basis
set -ex

which mc >> /dev/null || (echo 'Minio client executable not found'; exit 1)

rm -r minio-data || true
rm -r ../../.Build/minio-data || true
mkdir ../../.Build/minio-data

docker stop minio-setup || true
docker-compose run -d --rm --name minio-setup -p 9000:9000 minio
# minio needs some time to start up
sleep 5

mc alias remove typo3s3test || true
mc alias set typo3s3test http://127.0.0.1:9000 minioadmin minioadmin
mc mb typo3s3test/test-bucket/
mc anonymous set download typo3s3test/test-bucket/
mc admin user add typo3s3test test-key test-secretkey
mc admin policy attach typo3s3test readwrite --user test-key

mc cp --recursive ../../Tests/Functional/Bucketfiles/* typo3s3test/test-bucket/

docker stop minio-setup
cp -a ../../.Build/minio-data .
rm -r minio-data/.minio.sys/tmp/.trash/*
