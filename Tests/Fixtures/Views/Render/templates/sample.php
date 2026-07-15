<?php
/**
 * Fixture template for Tests/Unit/Views/Render/ViewTest.php. Calls no
 * WordPress function so Render can be tested without bootstrapping WP.
 *
 * @var array<string, mixed> $data {
 *     @type string $message Text to echo verbatim.
 * }
 */

declare(strict_types=1);

echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixture stub, not real rendered output.
