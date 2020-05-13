<?php


namespace Cloudia\Cli;

use Phore\Core\Exception\NotFoundException;
use Phore\CliTools\Helper\GetOptResult;
use Phore\CliTools\PhoreAbstractCli;
use Phore\Core\Exception\InvalidDataException;
use Phore\FileSystem\PhoreTempFile;
use Phore\FileSystem\PhoreDirectory;
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
                $skip_prompt = false;
                $passphrase = "";
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i++) {
                        if($argv[$i] === "--yes" || $argv[$i] === "-y") {
                            $skip_prompt = true;
                        } else if($argv[$i] === "--passphrase" || $argv[$i] === "-p") {
                            $passphrase = $argv[$i+1];
                            $i++;
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }
                if(!$skip_prompt) {
                    $isRequired = "Y" . PHP_EOL;
                    if(phore_file($cloudiaFile)->isFile()) {
                        $this->out("Key file(cloudia.yaml) exists, Do you want to create new?(Y/N): ");
                        $handle = fopen ("php://stdin","r");
                        $isRequired = fgets($handle);
                        fclose($handle);
                    } 
                    if(strcasecmp("Y" . PHP_EOL, $isRequired) == 0) {
                        if(empty($passphrase)) {
                            $this->out("Enter passphrase for encrypting the private key(Leave empty to generate):");
                            $handle = fopen ("php://stdin","r");
                            $passphrase = fgets($handle);
                            fclose($handle);
                        }
                    } else {
                        throw new InvalidDataException("New keys are NOT generated");
                    }
                }
                $passphrase = trim($passphrase);
                if(empty($passphrase)) {
                    $passphrase = phore_random_str(45);
                    $this->out("Random passphrase->" . PHP_EOL);
                    $this->out($passphrase . PHP_EOL);
                }
                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $keys = $asyncEncrypter->createKeyPair();
                $keys["private_key"] = $syncEncrypter->encrypt($keys["private_key"]);

                phore_file($cloudiaFile)->set_yaml(["sec" => $keys]);
                $this->out("Keys saved in-> " . $cloudiaFile . PHP_EOL);
            },

            "encrypt_async" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $tmpFile = new PhoreTempFile();
                $folder = getcwd();
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i+=2) {
                        if($argv[$i] === "--volume" || $argv[$i] === "-v") {
                            $folder = $argv[$i+1];
                        }
                        else if($argv[$i] === "--input" || $argv[$i] === "-i") {
                            $secret = $argv[$i+1];
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }
                $cloudiaFile = $folder . "/cloudia.yaml";
                if(phore_file($cloudiaFile)->isFile()) {
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $publicKey = $keys["sec"]["public_key"];
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found. Run 'cloudia init' to generate or specify the folder with -f flag");
                }       
                if(empty($secret)) {
                    $this->out("Enter the secret which needs to be encrypted:" . PHP_EOL);
                    passthru("editor $tmpFile");
                    $secret = $tmpFile->get_contents();
                }
                $secret=trim($secret);
                if(!empty($secret)) {
                    $this->out("Encrypted Secret->" . PHP_EOL);
                    $this->out($asyncEncrypter->encrypt($secret, $publicKey) . PHP_EOL);    
                } else {
                    throw new InvalidDataException("Invalid secret: secret is blank or only whitespaces");
                }
                             
            },

            "decrypt_async" => function (array $argv) {
                $folder = getcwd();
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $extension = [];
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i+=2) {
                        if($argv[$i] === "--volume" || $argv[$i] === "-v") {
                            $folder = $argv[$i+1];
                        }
                        else if($argv[$i] === "--passphrase" || $argv[$i] === "-p") {
                            $passphrase = $argv[$i+1];
                        } else if($argv[$i] === "--type" || $argv[$i] === "-t") {
                           array_push($extension, $argv[$i+1]);
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }
                //Default to yaml and yml files
                if(empty($extension)) {
                    array_push($extension, "yaml", "yml");
                }
                $cloudiaFile = $folder . "/cloudia.yaml";
                if(phore_file($cloudiaFile)->isFile()) {
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $privateKey = $keys["sec"]["private_key"];
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found in " . $folder);
                }
                  
                if(empty($passphrase)) {
                    $this->out("Enter passphrase for decrypting the private key:");
                    $handle = fopen ("php://stdin","r");
                    $passphrase = fgets($handle);
                    fclose($handle);
                }

                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $privateKey = $syncEncrypter->decrypt($privateKey);

                $this->out("Recursively looking for ". implode(",",$extension) ." files in -> " . $folder . PHP_EOL);
                $phoreDir = new PhoreDirectory($folder);
                $phoreDir -> walkR(function($file) use ($asyncEncrypter, $privateKey, $extension) {
                    $phoreFile=phore_file($file);
                    if(in_array($phoreFile->getExtension(), $extension)) {
                        $this->out("Processing...." . $phoreFile->getFilename() . PHP_EOL);
                        $contents = $phoreFile->get_contents();
                        preg_match_all("/{sec-(.+?)}/", $contents, $secrets, PREG_SET_ORDER);
                        if(!empty($secrets)) {
                            $this->out("Replacing secrets in file -> " . $phoreFile->getFilename() . PHP_EOL);
                            foreach($secrets as $secret) {
                                $contents = str_replace($secret[0], str_replace("\n", "", 
                                                $asyncEncrypter->decrypt($secret[1], $privateKey)), $contents);
                            }
                            $phoreFile->set_contents($contents);
                        }
                    } 
                 });  
            },
        ]);
    }
}