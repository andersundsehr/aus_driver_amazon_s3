# Changelog

## 3.0.0

### Added

- Add opt-in `strict` file hashing. The requested hash must exist in S3 object
  metadata (for example, `hash-sha1`); missing metadata raises a `RuntimeException`
  without downloading the object. Existing hash modes and the default remain unchanged.
- Add regression coverage for hashing, metadata caching, folder existence,
  leading-slash identifiers, mutation invalidation, empty replacements, and stream
  flush failures.

### Fixed

- Respect the configured base folder when reading stored hashes or downloading
  objects to calculate hashes.
- Strip leading slashes from identifiers used for S3 requests at the bucket root.
- Cache LIST metadata under the correct storage-relative identifier without
  duplicating the folder prefix.
- Read the S3 LIST response's `Size` field and preserve zero-byte sizes from both
  LIST and HEAD responses.
- Include endpoint, region, bucket, and base folder in cache keys to prevent
  collisions between storage configurations.
- Invalidate metadata and listing caches after content writes, source moves through
  `addFile()`, and recursive child-file deletion.
- Detect incomplete writes and upload failures through explicit stream flushing.
- Return success when replacing a file with empty contents.
- Remove temporary files after failed downloads and throw a `RuntimeException`
  retaining the original exception.
- Throw `FolderDoesNotExistException` for missing folders so TYPO3 can enter its
  folder-creation flow.

### Changed

- Complete partial LIST metadata through HEAD when hashing or requesting MIME type
  or all file properties. Reuse complete metadata, including confirmed absence of
  a stored hash, without invalidating unrelated listing caches.
- Reuse listed-folder information to avoid redundant folder-existence requests.
- Reuse a shared metadata adapter for HEAD and LIST response conversion.
- Include custom S3 metadata in file information without overwriting existing core
  property values.
- Clarify hash configuration labels and document hash modes, metadata requirements,
  and reindexing implications.
- Link the functional-test containers to MinIO.

### Compatibility notes

- `EXTENSION_NAME`, `FILTER_*`, `ROOT_FOLDER_IDENTIFIER`, and `FILE_CONTENT_HASH_*`
  are now protected constants. Subclasses can still access them, but external code
  can no longer access them directly. Configure hash modes through the existing
  string settings (`ignore`, `receive`, `force`, `strict`). `DRIVER_TYPE` and
  `EXTENSION_KEY` remain public with explicit visibility.

- Public method signatures and existing hash-setting defaults are unchanged.
- Subclasses overriding the following protected methods must accept the added
  optional parameters with compatible signatures:

  ```php
  protected function getMetaInfo(string $identifier, bool $requireCompleteMetadata = false): ?array
  protected function flushMetaInfoCache(string $identifier, bool $resetRequestCache = true): void
  ```

  Defaults preserve existing calls with one argument, but do not make old
  one-parameter subclass overrides compatible. Overrides should honor the new
  flags and forward them when calling the parent implementation.
- `normalizeIdentifier()` now declares its by-reference argument as `string`.
  Custom callers should supply string identifiers; an untyped subclass override
  remains compatible.
- Missing-folder lookups now throw instead of returning fabricated folder
  information. Failed writes and temporary downloads now reliably surface errors.
- Hash and MIME-type reads reuse cached HEAD metadata. Changes made outside the
  driver can remain invisible until cache expiry or invalidation. The default
  caches are request-local; custom persistent caches can retain metadata longer.
- At the bucket root, `/file.txt` now addresses the S3 key `file.txt`. Integrations
  relying on literal object keys beginning with `/` must account for this change.
- The new cache-key format stops reusing old cache entries; those entries can be
  removed through normal cache cleanup.
- Selecting `strict` requires matching hash metadata on the objects being hashed.
  Changing the setting does not backfill metadata or refresh hashes already stored
  in TYPO3's file index.
