<?php


namespace Cloudia\Cli;


use Phore\CliTools\Helper\GetOptResult;
use Phore\CliTools\PhoreAbstractCli;

class CloudiaCli extends PhoreAbstractCli
{

    public function __construct()
    {
        parent::__construct(
            "IaC service tool",
            __DIR__ . "/../cli_help.txt",
            "p:m",
            ["file"]

        );
    }


    /**
     * @param array $argv
     * @param int $argc
     * @param GetOptResult $opts
     * @throws \Phore\CliTools\Ex\UserInputException
     */
    protected function main(array $argv, int $argc, GetOptResult $opts)
    {
        $this->execMap([
            "create_keypair" => function (array $argv) {
                $this->out("Hello world", print_r($argv, true));
            },
            "say_hello" => function (array $argv) {
                $this->out("Hello!" . PHP_EOL);
            }
        ]);
    }
}