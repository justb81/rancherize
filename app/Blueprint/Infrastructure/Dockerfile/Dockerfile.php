<?php namespace Rancherize\Blueprint\Infrastructure\Dockerfile;

/**
 * Class Dockerfile
 * @package Rancherize\Blueprint\Infrastructure
 */
class Dockerfile {

	/**
	 * @var string
	 */
	protected $from = '';

	/**
	 * @var string
	 */
	protected $command = '';

	/**
	 * @var string
	 */
	protected $entrypoint = '';

	/**
	 * @var string[]
	 */
	protected $volumes;

	/**
	 * @var string[]
	 */
	protected $copies = [];

	/**
	 * @var string[]
	 */
	protected $runCommands = [];

	/**
	 * @param string $from
	 */
	public function setFrom(string $from) {
		$this->from = $from;
	}

	/**
	 * @return string
	 */
	public function getFrom(): string {
		return $this->from;
	}

	/**
	 * @return string
	 */
	public function getCommand(): string {
		return $this->command;
	}

	/**
	 * @param string $command
	 */
	public function setCommand(string $command) {
		$this->command = $command;
	}

	/**
	 * @return string
	 */
	public function getEntrypoint(): string {
		return $this->entrypoint;
	}

	/**
	 * @param string $entrypoint
	 */
	public function setEntrypoint(string $entrypoint) {
		$this->entrypoint = $entrypoint;
	}

	/**
	 * @return \string[]
	 */
	public function getVolumes(): array {
		return $this->volumes;
	}

	/**
	 * @param string $volume
	 */
	public function addVolume(string $volume) {

		// [$volume] from having the same volume set multiple times
		$this->volumes[$volume] = $volume;
	}

	/**
	 * @return array
	 */
	public function getCopies(): array {
		return $this->copies;
	}

	/**
	 * @param string $from
	 * @param string $target
	 */
	public function copy(string $from, string $target) {
		$this->copies[$from] = $target;
	}

	/**
	 * @return \string[]
	 */
	public function getRunCommands(): array {
		return $this->runCommands;
	}

	/**
	 * @param $command
	 */
	public function run($command) {
		$this->runCommands[$command] = $command;
	}
}