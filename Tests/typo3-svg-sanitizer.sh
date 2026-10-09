#!/usr/bin/env bash

set -euo pipefail

# Exercise TYPO3's adapter against the actual security-fixed release, not its CI alias.
php <<'PHP'
<?php

require '.Build/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

check(
    Composer\InstalledVersions::getPrettyVersion('enshrined/svg-sanitize') === '1.0.0',
    'Expected the actual security-fixed svg-sanitize 1.0.0 release'
);

$sanitizer = new TYPO3\CMS\Core\Resource\Security\SvgSanitizer();
$svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
    . '<rect id="safe" width="10" height="10"/>'
    . '<image href="https://example.invalid/remote.svg"/></svg>';
$clean = $sanitizer->sanitizeContent($svg);
check(str_contains($clean, 'id="safe"'), 'Valid SVG content was removed');
check(!str_contains($clean, 'onload'), 'Event handler was not removed');
check(!str_contains($clean, 'example.invalid'), 'Remote reference was not removed');

$entity = '<!DOCTYPE svg [<!ENTITY Tab "#">]>'
    . '<svg xmlns="http://www.w3.org/2000/svg"><a href="&Tab;javascript:alert(1)"/></svg>';
check($sanitizer->sanitizeContent($entity) === '', 'DTD entity bypass was not rejected');

$document = new DOMDocument();
check($document->loadXML($svg), 'Failed to load SVG fixture');
$node = $sanitizer->sanitizeNode($document->documentElement);
$cleanNode = $document->saveXML($node);
check(str_contains($cleanNode, 'id="safe"'), 'sanitizeNode removed valid content');
check(!str_contains($cleanNode, 'onload'), 'sanitizeNode retained an event handler');
check(!str_contains($cleanNode, 'example.invalid'), 'sanitizeNode retained a remote reference');

$file = tempnam(sys_get_temp_dir(), 'typo3-svg-');
check($file !== false, 'Failed to create temporary SVG fixture');
try {
    check(file_put_contents($file, $svg) !== false, 'Failed to write SVG fixture');
    $sanitizer->sanitizeFile($file);
    check(file_get_contents($file) === $clean, 'sanitizeFile produced unexpected output');
} finally {
    unlink($file);
}

echo "PASS: TYPO3 SVG content, node, file and DTD entity regression checks\n";
PHP
