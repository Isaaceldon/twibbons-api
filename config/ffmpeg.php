<?php

return [
    'ffmpeg.binaries' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
    'timeout' => env('FFMPEG_TIMEOUT', 3600),
    'ffmpeg.threads' => env('FFMPEG_THREADS', 12),
];
