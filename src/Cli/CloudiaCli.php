<?php


namespace Cloudia\Cli;

use Phore\Core\Exception\NotFoundException;
use Phore\CliTools\Helper\GetOptResult;
use Phore\CliTools\PhoreAbstractCli;
use Phore\FileSystem\PhoreTempFile;
use Phore\Core\Helper\PhoreSecretBoxAsync;
use Phore\Core\Helper\PhoreSecretBoxSync;

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
            "init" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $cloudiaFile = getcwd() . "/cloudia.yaml";

                if(phore_file($cloudiaFile)->isFile()) {
                    $this->out("Key file(cloudia.yaml) exists, Do you want to create new?(Y/N): ");
                    $handle = fopen ("php://stdin","r");
                    $isRequired = fgets($handle);
                    fclose($handle);
                } else {
                    $isRequired = "Y" . PHP_EOL;
                }
                if(strcasecmp("Y" . PHP_EOL, $isRequired) == 0) {
                    $this->out("Enter passphrase for encrypting the private key(Leave empty to generate):");
                    $handle = fopen ("php://stdin","r");
                    $passphrase = fgets($handle);
                    fclose($handle);
    
                    if(ctype_space($passphrase)) {
                        $passphrase = phore_random_str(45);
                        $this->out("Random passphrase->" . PHP_EOL);
                        $this->out($passphrase . PHP_EOL);
                    }
                    $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                    $keys = $asyncEncrypter->createKeyPair();
                    $keys["private_key"] = $syncEncrypter->encrypt($keys["private_key"]);
    
                    phore_file($cloudiaFile)->set_yaml(["sec" => $keys]);
                    $this->out("Keys saved in-> " . $cloudiaFile . PHP_EOL);
                } else {
                    $this->out("New keys are NOT generated " . PHP_EOL);
                } 
            },

            "encrypt_async" => function (array $argv) {
                $cloudiaFile = getcwd() . "/cloudia.yaml";
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $tmpFile = new PhoreTempFile();

                if(phore_file($cloudiaFile)->isFile()) {
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $publicKey = $keys["sec"]["public_key"];
                    $this->out("Enter the secret which needs to be encrypted:" . PHP_EOL);
                    passthru("editor $tmpFile");
                    $secret = $tmpFile->get_contents();
                    $this->out("Encrypted Secret->" . PHP_EOL);
                    $this->out($asyncEncrypter->encrypt($secret, $publicKey) . PHP_EOL);
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found. Run 'cloudia init' to generate");
                }         
            },

            "decrypt_async" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $cloudiaFile = getcwd() . "/cloudia.yaml";
                if(phore_file($cloudiaFile)->isFile()) {
                    if(empty($argv)) {
                        $this->out("Enter passphrase for decrypting the private key:");
                        $handle = fopen ("php://stdin","r");
                        $passphrase = fgets($handle);
                        fclose($handle);
                    } else {
                        $passphrase = $argv[0];
                    }
                    
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $privateKey = $keys["sec"]["private_key"];
                    $this->out("Enter the secret which needs to be decrypted:");
                    $handle = fopen ("php://stdin","r");
                    $secret = fgets($handle);
                    fclose($handle);
                    $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                    $privateKey=$syncEncrypter->decrypt($privateKey);
                    $this->out("Decrypted Secret->" . PHP_EOL);
                    $this->out($asyncEncrypter->decrypt($secret, $privateKey) . PHP_EOL);
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found.");
                }       
            },

            "say_hello" => function (array $argv) {
                $this->out("Hello!" . PHP_EOL);
            },

            // open the default editor to enter some secret
            "open_editor" => function(array $argv) {

                $tmpFile = new PhoreTempFile();

                // Open the editor
                passthru("editor $tmpFile");

                $this->out("You wrote:" . $tmpFile->get_contents());

                // Temp file should be deleted on __destuct()
            }
        ]);
    }
}