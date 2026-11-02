<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Restores only to a newly created random database under a separate restricted account. */
final class MysqlRestoreVerifier
{
    public function verify(string $dump, array $expectedCounts): void
    {
        $target = config('nansan.restore_mysql');
        if (! $target['host'] || ! $target['username'] || ! $target['password']) {
            throw new RuntimeException('An isolated MySQL restore account must be configured.');
        }
        // Operational requirement: grant this account only nansan_restore_%.*.
        if ($target['username'] === config('database.connections.mysql.username')) {
            throw new RuntimeException('Restore account must differ from the application account.');
        }
        if (! preg_match('/^[a-zA-Z0-9.:-]+$/D', $target['host']) || ! ctype_digit((string) $target['port'])) {
            throw new RuntimeException('Invalid restore endpoint.');
        }
        $database = 'nansan_restore_'.bin2hex(random_bytes(12));
        if ($database === config('database.connections.mysql.database')) {
            throw new RuntimeException('Source database cannot be a restore target.');
        }
        $pdo = new PDO('mysql:host='.$target['host'].';port='.$target['port'].';charset=utf8mb4', $target['username'], $target['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $created = false;
        try {
            // No IF NOT EXISTS: an existing database is never reused or dropped.
            $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created = true;
            $input = fopen($dump, 'rb');
            try {
                $process = new Process([$target['client'], '--host='.$target['host'], '--port='.$target['port'], '--user='.$target['username'], '--database='.$database, '--binary-mode=1'], dirname($dump), ['MYSQL_PWD' => $target['password']], $input, 300);
                $process->disableOutput();
                $process->run();
            } finally {
                fclose($input);
            }
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Isolated MySQL restore failed.');
            }
            $pdo->exec('USE `'.$database.'`');
            foreach ($expectedCounts as $table => $count) {
                if (! in_array($table, ['users', 'tools', 'documentation_articles'], true)) {
                    throw new RuntimeException('Unexpected verification table.');
                }
                if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() !== $count) {
                    throw new RuntimeException('Restored row count mismatch.');
                }
            }
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                    throw new RuntimeException('Unexpected restored table name.');
                }
                $result = $pdo->query('CHECK TABLE `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
                if (! collect($result)->contains(fn ($r) => $r['Msg_type'] === 'status' && $r['Msg_text'] === 'OK')) {
                    throw new RuntimeException('Restored table check failed.');
                }
            }
        } finally {
            if ($created) {
                $pdo->exec('DROP DATABASE `'.$database.'`');
            }
        }
    }
}
