PLATFORM ?= \
  -P ubuntu-latest=ghcr.io/catthehacker/ubuntu:act-24.04 \
  -P ubuntu-24.04=ghcr.io/catthehacker/ubuntu:act-24.04
ROOT_DIR := $(shell pwd)
ACT_ARGS ?= --artifact-server-path $(ROOT_DIR)/.Build/.cache/artifacts \
			--cache-server-path    $(ROOT_DIR)/.Build/.cache/caches \
			--container-options " --add-host minio:host-gateway \
			                      -e ROOT_DIR=$(ROOT_DIR)"
ACT_BIN  := .Build/bin/act

.PHONY: functional-tests tests unit-tests matrix

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

$(ACT_BIN):
	mkdir -p .Build/bin
	curl -sL https://github.com/nektos/act/releases/download/v0.2.81/act_Linux_x86_64.tar.gz \
	| tar -xz -C .Build/bin act
	chmod +x $@

run: $(ACT_BIN)
	$(ACT_BIN) workflow_dispatch $(PLATFORM) $(ACT_ARGS)
