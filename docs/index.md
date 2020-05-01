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

**Install with npm**

```bash
npm install -g infracamp/cloudia
```

**Install with composer**

```bash
composer install -g infracamp/cloudia
```

## Usage

**Print help**
```bash
$ cloudia -h
```

### Initialize the repository

```bash
cloudia init
```

Will create a `cloudia.yml` in the current directory containing the
a public key to encrypt secrets and the symetricly encrypted private key
to decrypt the passwords in your CI runner.

Output:

```
A new keypair was generated in cloudia.yml

Your decrypt key is

  E1-2i4...==

please copy this decrypt key into your Jenkins/GitlabCI/GithubActions
Secret "CLOUDIA_SECRET"
```