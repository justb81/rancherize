<?php

$container = new \Pimple\Container();

$container['file-loader'] = function($c) {
	return new \Rancherize\File\FileLoader();
};

$container['file-writer'] = function($c) {
	return new \Rancherize\File\FileWriter();
};

$container['configuration'] = function($container) {
	return new \Rancherize\Configuration\ArrayConfiguration();
};

$container['loader'] = function($c) {
	return new \Rancherize\Configuration\Loader\JsonLoader($c['file-loader']);
};

$container['writer'] = function($c) {
	return new \Rancherize\Configuration\Writer\JsonWriter($c['file-writer']);
};

$container['global-config-service'] = function($c) {
	return new \Rancherize\Configuration\Services\GlobalConfiguration(
		$c['loader'],
		$c['writer']
	);
};

$container['project-config-service'] = function($c) {
	return new \Rancherize\Configuration\Services\ProjectConfiguration(
		$c['loader'],
		$c['writer']
	);
};


$container['blueprint-factory'] = function($c) {
	return new \Rancherize\Blueprint\Factory\ConfigurationBlueprintFactory($c['configuration']);
};

$container['config-wrapper'] = function($c) {
	return new \Rancherize\Configuration\Services\ConfigWrapper(
		$c['global-config-service'],
		$c['project-config-service'],
		$c['configuration']
	);
};

$container['validate-service'] = function($c) {
		return new \Rancherize\Services\ValidateService();
};

$container['build-service'] = function($c) {
	return new \Rancherize\Services\BuildService($c['validate-service']);
};

$container['docker-service'] = function($c) {
	return new \Rancherize\Services\DockerService();
};

$container['api-service'] = function($c) {
	return new \Rancherize\RancherAccess\ApiService\CurlApiService();
};

$container['rancher-service'] = function($c) {
	return new \Rancherize\RancherAccess\RancherService( $c['api-service'] );
};

$container['blueprint-service'] = function($c) {
	return new \Rancherize\Services\BlueprintService($c['blueprint-factory']);
};

if( ! function_exists('container') ) {

	/**
	 * Get an instance from the container
	 *
	 * @param string $instance
	 * @return \Pimple\Container
	 */
	function container(string $instance = null) {
		global $container;

		if($instance === null)
			return $container;

		return $container[$instance];
	}
}
