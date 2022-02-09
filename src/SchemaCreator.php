<?php

namespace LaraSurf\LaraSurf;

use PDO;

class SchemaCreator
{
    /**
     * Creates a database schema on the specified MySQL host for use by a Laravel application.
     *
     * @param string $project_name
     * @param string $environment
     * @param string $db_host
     * @param string $db_port
     * @param string $db_username
     * @param string $db_password
     * @return string|false
     */
    public function createSchema(
        string $project_name,
        string $environment,
        string $db_host,
        string $db_port,
        string $db_username,
        string $db_password
    ): string|false
    {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;', $db_host, $db_port), $db_username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $database_name = str_replace('-', '_', $project_name) . '_' . $environment;

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
