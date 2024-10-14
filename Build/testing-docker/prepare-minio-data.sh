#! /bin/bash

# enable job control
set -m

minio server /minio-data &
# Wait for minio to come up
sleep 5
mc alias set typo3s3test http://127.0.0.1:9000 minioadmin minioadmin
mc mb typo3s3test/test-bucket/
mc anonymous set download typo3s3test/test-bucket/
mc admin user add typo3s3test test-key test-secretkey
mc admin policy attach typo3s3test readwrite --user test-key

mc cp --recursive /app/Tests/Functional/Bucketfiles/* typo3s3test/test-bucket/

fg
