---
layout: scrollspy
title: CloudIA - Cloud Infrastructure Automation Tool
description: |
    CloudIA is a command line utility to manage the deployments
    of your cluster environment on kubernetes or docker swarm
    directly from your infrastructure git repository. CloudIA
    can encrypt sensitive data, provision machines by using
    ssh, deploy changed services directly from your CI/CD pipeline.
    CloudIA is aimed to work closely with Terraform, Gitlab CI,
    Github Actions, Jenkins.
  
    
---

CloudIA is aimed to be run both on the CI/CD Stack and by
your DevOps Developers.

## Installation

**Install with composer**

```bash
composer global install infracamp/cloudia
```

## Usage

**Print help**
```bash
$ cloudia -h
```

### Initialize the Repository

```bash
cloudia init
```

Will create a `cloudia.yml` in the current directory containing the
a public key to encrypt secrets and the symmetricly encrypted private key
to decrypt the passwords in your CI runner.

Arguments:

-y   - -yes          | Give yes to all prompts (Overwite cloudia.yaml file if exists and auto generate passphrase if -p flag not provided).
-p   - -passphrase   | Passphrase for encrypting the private key. Will prompt if not provided.

Output:

```
A new keypair was generated in cloudia.yml

Your decrypt key is

  E1-2i4...==

please copy this decrypt key into your Jenkins/GitlabCI/GithubActions
Secret Name "CLOUDIA_SECRET"
```

### Encrypt Secrets

```bash
cloudia encrypt
```

Will encrypt the secret using the public key in `cloudia.yml` which is in the current directory unless specified otherwise.


Arguments:

-C  - -directory    | Directory where cloudia.yaml file exists. Defaults to current directory.
-I  - -input        | Secret which needs to be encrypted. Will prompt if not provided.

Output:

```
Your Encrypted Secret is

  {ENC-EA1-7ij....==}

Please copy this encrypted secret into the pod/kube/key files
```

### Decrypt Secrets

```bash
cloudia decrypt
```

Will look for encrypted secrets in files recursively in the current folder, decrypt using the private key in `cloudia.yml` and CLOUDIA_SECRET.
The secrets are then replaced in the file.


Arguments:

-C   - -directory    | cloudia.yaml must be available in this folder. Recursively look for files for secrets to be decrypted in this folder. Defaults to current directory. 
-p   - -passphrase   | Passphrase for decrypting the private key. Will prompt if not provided or environment variable CLOUDIA_SECRET is not provided.
-t   - -type         | Extension of files which contains secrets to be decrypted. Defaults to values yaml & yml.

Output:

```
Recursively looking for yaml, yml files in /opt/
Processing....kube.yaml
Replacing secrets in file kube.yaml.
All files processed successfully.

```