<?php
/**
 * Minimal fixture template for Tests/Unit/Views/Render/ViewTest.php.
 *
 * Deliberately calls no WordPress function so Render::view() can be unit
 * tested (Brain\Monkey + Mockery) without bootstrapping WordPress. Echoes
 * $data['message'] verbatim to prove $data reaches the template unmodified.
 *
 * @var array<string, mixed> $data {
 *     @type string $message Text to echo verbatim.
 * }
 */

declare(strict_types=1);

echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixture-only stub with no WordPress dependency (used exclusively by Render's own pure-PHP unit tests), not real rendered output.
