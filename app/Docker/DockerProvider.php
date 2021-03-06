<?php namespace Rancherize\Docker;

use Rancherize\Plugin\Provider;
use Rancherize\Plugin\ProviderTrait;

/**
 * Class DockerProvider
 * @package Rancherize\Docker
 */
class DockerProvider implements Provider {

	use ProviderTrait;

	/**
	 */
	public function register() {
		$this->container[DockerAccessService::class] = function($c) {
			return new DockerAccessConfigService($c['event']);
		};
	}

	/**
	 */
	public function boot() {
	}
}