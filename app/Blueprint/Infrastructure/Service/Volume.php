<?php namespace Rancherize\Blueprint\Infrastructure\Service;

/**
 * Class Volume
 * @package Rancherize\Blueprint\Infrastructure\Service
 *
 * @FIXME: Mit Rancherize\Blueprint\Infrastructure\Service vereinigen.
 */
class Volume {

	/**
	 * @var
	 */
	protected $externalPath;

	/**
	 * @var
	 */
	protected $internalPath;


	/**
	 * @var string
	 */
	protected $driver = '';

	/**
	 * @var array
	 */
	private $options = [];

	/**
	 * @return string
	 */
	public function getDriver(): string {
		return $this->driver;
	}

	/**
	 * @param string $driver
	 */
	public function setDriver( string $driver ) {
		$this->driver = $driver;
	}

	/**
	 * @return mixed
	 */
	public function getExternalPath() {
		return $this->externalPath;
	}

	/**
	 * @param mixed $externalPath
	 */
	public function setExternalPath( $externalPath ) {
		$this->externalPath = $externalPath;
	}

	/**
	 * @return mixed
	 */
	public function getInternalPath() {
		return $this->internalPath;
	}

	/**
	 * @param mixed $internalPath
	 */
	public function setInternalPath( $internalPath ) {
		$this->internalPath = $internalPath;
	}


	/**
	 * @param array $options
	 */
	public function setOptions( array $options ) {
		$this->options = $options;
	}

	/**
	 * @return array
	 */
	public function getOptions(): array {
		return $this->options;
	}
}