#!/bin/bash

set -e

PROJECT_NAME=$(cat larasurf.json | jq -r '."project-name"')
PROJECT_ID=$(cat larasurf.json | jq -r '."project-id"')
ENVIRONMENT=$1

PATH_PREFIX="/${PROJECT_NAME}-${PROJECT_ID}/${ENVIRONMENT}/"

REPLACE="          Secrets:"

NEXT_TOKEN=""

while [[ "${NEXT_TOKEN}" != "null" ]]; do
    if [[ -z "${NEXT_TOKEN}" ]]; then
        RESPONSE=$(aws ssm get-parameters-by-path --path ${PATH_PREFIX})
    else
        RESPONSE=$(aws ssm get-parameters-by-path --path ${PATH_PREFIX} --starting-token "${NEXT_TOKEN}")
    fi

    NEXT_TOKEN=$(echo ${RESPONSE} | jq -r .NextToken)

    PARAMETERS=$(echo ${RESPONSE} | jq -r ".Parameters | map(\"            - Name: \" + (.Name | sub(\"^${PATH_PREFIX}\"; \"\")) + \"##NEWLINE##              ValueFrom: \" + .ARN) | .[]")
    REPLACE="${REPLACE}##NEWLINE##${PARAMETERS}"
done

awk -v SECRETS="${REPLACE}" -v NEWLINE="\n" '{
    gsub(/          Secrets: #LARASURF_SECRETS#/, SECRETS);
    gsub(/##NEWLINE##/, NEWLINE);
    print;
}' .cloudformation/infrastructure.yml > infrastructure.yml
