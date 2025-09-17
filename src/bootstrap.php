<?php
declare(strict_types=1);

if (!extension_loaded('sodium')) {
    throw new RuntimeException('ext-sodium is required.');
}
if (!extension_loaded('hash')) {
    throw new RuntimeException('ext-hash is required.');
}