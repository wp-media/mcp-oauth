<?php
/**
 * Path-traversal negative fixture for Tests/Unit/Views/Render/ViewTest.php.
 *
 * Deliberately placed where `Tests/Fixtures/Views/Render/templates/../../secret.php`
 * would resolve to (two directories above the templates/ dir used by that
 * test's Render instance). If Render::view()'s basename() confinement were
 * ever removed, a `$view` of `'../../secret'` would resolve here instead of
 * throwing \RuntimeException; this file's content should never be observed
 * by that test.
 */

declare(strict_types=1);

echo 'SHOULD_NOT_BE_RENDERED';
