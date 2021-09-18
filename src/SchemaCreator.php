<?php

namespace LaraSurf\LaraSurf;

use PDO;

class SchemaCreator
{
    public function __construct(
        protected string $project_name,
        protected string $environment,
        protected string $db_host,
        protected string $db_port,
        protected string $db_username,
        protected string $db_password,
    ) {
    }

    public function createSchema(): string|false
    {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;', $this->db_host, $this->db_port), $this->db_username, $this->db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $database_name = str_replace('-', '_', $this->project_name) . '_' . $this->environment;

        $result = $pdo->exec(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s;',
            $database_name,
            'utf8mb4',
            'utf8mb4_unicode_ci'
        ));

        if ($result === false) {
            return false;
        }

        return $database_name;
    }
}