<?php
/**
 * Path-traversal negative fixture. Sits where `../../secret` would resolve
 * to from the templates/ dir; should never be rendered by the test.
 */

declare(strict_types=1);

echo 'SHOULD_NOT_BE_RENDERED';
