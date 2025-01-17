.PHONY: functional-tests tests unit-tests

tests: unit-tests functional-tests

functional-tests: .Build/bin/phpunit .Build/Web/typo3conf/ext/aus_driver_amazon_s3
	 ./Build/Scripts/runTests.sh -p 8.2 -d sqlite -s functional\
	    -e "--display-warnings --display-notices --display-errors"

unit-tests: .Build/bin/phpunit .Build/Web/typo3conf/ext/aus_driver_amazon_s3
	 ./Build/Scripts/runTests.sh -p 8.2 -d sqlite -s unit\
	    -e "--display-warnings --display-notices --display-errors"

.Build/bin/phpunit:
	composer install

.Build/Web/typo3conf/ext/aus_driver_amazon_s3:
	mkdir -p .Build/Web/typo3conf/ext/
	ln -sfn ../../../../ .Build/Web/typo3conf/ext/aus_driver_amazon_s3
