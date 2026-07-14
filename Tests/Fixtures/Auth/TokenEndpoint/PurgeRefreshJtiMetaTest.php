<?php
/**
 * Scenarios for Tests\Integration\Auth\TokenEndpoint\PurgeRefreshJtiMetaTest::testShouldPurgeRefreshJtiMetaAccordingToConfig
 *
 * `config.pass_uuid` controls whether the `$item` array passed to
 * purge_refresh_jti_meta() carries the seeded session's uuid.
 */

return [
	'testShouldPurgeMetaForGivenUuid'    => [
		'config'   => [
			'pass_uuid' => true,
		],
		'expected' => [
			'should_purge' => true,
		],
	],
	'testShouldDoNothingWhenUuidMissing' => [
		'config'   => [
			'pass_uuid' => false,
		],
		'expected' => [
			'should_purge' => false,
		],
	],
];
