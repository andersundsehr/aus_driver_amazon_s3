<?php
/**
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0.
 */
namespace AWS\CRT;
use AWS\CRT\CRT;

final class Log {
    const NONE = 0;
    const FATAL = 1;
    const ERROR = 2;
    const WARN = 3;
    const INFO = 4;
    const DEBUG = 5;
    const TRACE = 6;

    public static function toStdout(): void {
        CRT::log_to_stdout();
    }

    public static function toStderr(): void {
        CRT::log_to_stderr();
    }

    public static function toFile($filename): void {
        CRT::log_to_file($filename);
    }

    public static function toStream($stream): void {
        assert(get_resource_type($stream) == "stream");
        CRT::log_to_stream($stream);
    }

    public static function stop(): void {
        CRT::log_stop();
    }

    public static function setLogLevel($level): void {
        assert($level >= self::NONE && $level <= self::TRACE);
        CRT::log_set_level($level);
    }

    public static function log($level, $message): void {
        CRT::log_message($level, $message);
    }
}
