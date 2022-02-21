<div align="center">
  <a href="https://larasurf.com">
    <img src="https://twemoji.maxcdn.com/svg/1f30a.svg" alt="Logo" width="80" height="80">
  </a>
<h1 align="center">LaraSurf</h1>

  <p align="center">
    LaraSurf combines Docker, CircleCI, and AWS to create an end-to-end solution for generating, implementing, and deploying Laravel applications.
    <br />
    <br />
    <a href="https://larasurf.com/how-it-works"><strong>How it works</strong></a>
    &bull;
    <a href="https://larasurf.com/docs"><strong>Documentation</strong></a>
    &bull;
    <a href="https://larasurf.com/new"><strong>New Project</strong></a>
    <br />
  </p>
</div>

[![Latest Version](https://img.shields.io/github/v/tag/larasurf/larasurf?label=latest&sort=semver)](https://github.com/larasurf/larasurf/releases)
[![CircleCI](https://circleci.com/gh/larasurf/larasurf/tree/main.svg?style=svg)](https://circleci.com/gh/larasurf/larasurf/?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Resources
* [AWS Cost Estimate Worksheet](https://docs.google.com/spreadsheets/d/1-BHba2Z1FiU84Kkw7YAyNgDhNnARdYdwOnpxr-uQYP4)

## Project Roadmap
[ ] Optimize Laravel for production in Dockerfile<br/>
[ ] Lock down CircleCI IAM user permissions<br/>
[ ] Scan both container images even if first scan fails<br/>
[ ] Queued closure support in Cloud Tinker<br/>
[ ] Prevent logging healthcheck requests in CloudWatch<br/>
[ ] Support more than 100 Hosted Zones in AWS account<br/>
[ ] Support regions other than `us-east-1`<br/>
[ ] Laravel Dusk support<br/>
[ ] Show stack change events in terminal<br/>
[ ] Test local development on Linux<br/>
[ ] Subcommand `rotate-keys` for cloud user AWS access keys<br/>
[ ] Add `cloud-certs` command for ACM management<br/>
[ ] Error messages audit/improvements<br/>
[ ] Optionally prompt for cloud-vars secret value during `put` operation<br/>
