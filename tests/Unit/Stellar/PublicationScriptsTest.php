<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Stellar;

use PHPUnit\Framework\TestCase;

final class PublicationScriptsTest extends TestCase
{
    private string $tmpDir;
    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 3);
        $this->tmpDir = sys_get_temp_dir() . '/amea-pub-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($this->tmpDir);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runCommand(array $command, array $env = []): array
    {
        $baseEnv = array_intersect_key($_SERVER + getenv(), array_flip(['PATH', 'HOME', 'TMPDIR', 'TMP', 'TEMP']));
        $proc = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->repoRoot,
            array_merge($baseEnv, $env)
        );
        $this->assertIsResource($proc);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * @return array<string, string>
     */
    private function baseRuntimeEnv(): array
    {
        return [
            'APP_ENV' => 'test',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_NAME' => 'amea_staging',
            'DB_USER' => 'amea_user',
            'DB_PASS' => 's3cr3t-db-pass-value',
            'PUBLIC_APP_URL' => 'https://demo.aeesgs.org',
            'STAFF_APP_URL' => 'https://bo.aeesgs.org',
            'MAIL_USER' => 'smtp@example.invalid',
            'MAIL_PASS' => 's3cr3t-smtp-key-value',
            'STORAGE_DRIVER' => 's3',
            'S3_PATH_STYLE' => 'false',
            'R2_ENDPOINT' => 'https://example.r2.cloudflarestorage.com',
            'R2_BUCKET_NAME' => 'amea-sandbox',
            'R2_ACCESS_KEY_ID' => 'r2-access-key',
            'R2_SECRET_ACCESS_KEY' => 's3cr3t-r2-secret-value',
            'CLOUDFLARE_ZONE_ID' => '0123456789abcdef0123456789abcdef',
            'CLOUDFLARE_API_TOKEN' => 's3cr3t-cf-purge-token',
            'CLOUDFLARE_CACHE_ENABLED' => '1',
            'UNRELATED_SECRET' => 'must-never-be-rendered',
        ];
    }

    public function testRenderRuntimeEnvWritesAllowlistedKeysWith0600AndNoSecretEcho(): void
    {
        $out = $this->tmpDir . '/runtime.env';
        $res = $this->runCommand(
            [PHP_BINARY, $this->repoRoot . '/scripts/stellar/render-runtime-env.php', '--output=' . $out, '--app-env=test'],
            $this->baseRuntimeEnv()
        );

        $this->assertSame(0, $res['code'], $res['stderr']);
        $this->assertFileExists($out);
        $this->assertSame(0, fileperms($out) & 0077, 'runtime.env must have 0600 permissions');

        $content = (string) file_get_contents($out);
        $this->assertStringContainsString("APP_ENV=test\n", $content);
        $this->assertStringContainsString("R2_BUCKET_NAME=amea-sandbox\n", $content);
        $this->assertStringContainsString("CLOUDFLARE_ZONE_ID=0123456789abcdef0123456789abcdef\n", $content);
        $this->assertStringContainsString("CLOUDFLARE_CACHE_ENABLED=1\n", $content);
        $this->assertStringNotContainsString('UNRELATED_SECRET', $content);
        $this->assertStringNotContainsString('must-never-be-rendered', $content);

        foreach (['s3cr3t-db-pass-value', 's3cr3t-smtp-key-value', 's3cr3t-r2-secret-value', 's3cr3t-cf-purge-token', 'must-never-be-rendered'] as $secret) {
            $this->assertStringNotContainsString($secret, $res['stdout']);
            $this->assertStringNotContainsString($secret, $res['stderr']);
        }

        $summary = json_decode($res['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('test', $summary['app_env']);
        $this->assertContains('DB_PASS', $summary['keys']);
        $this->assertNotContains('UNRELATED_SECRET', $summary['keys']);
    }

    public function testRenderRuntimeEnvRefusesSandboxBucketInProduction(): void
    {
        $out = $this->tmpDir . '/prod-bad.env';
        $res = $this->runCommand(
            [PHP_BINARY, $this->repoRoot . '/scripts/stellar/render-runtime-env.php', '--output=' . $out, '--app-env=production'],
            $this->baseRuntimeEnv()
        );

        $this->assertNotSame(0, $res['code']);
        $this->assertFileDoesNotExist($out);
        $this->assertStringContainsString('amea-sandbox', $res['stderr']);
    }

    public function testRenderRuntimeEnvRejectsNewlineInjectionAndMissingRequiredKeys(): void
    {
        $envWithNewline = $this->baseRuntimeEnv();
        $envWithNewline['DB_HOST'] = "127.0.0.1\nAPP_ENV=test";
        $out1 = $this->tmpDir . '/injected.env';
        $res1 = $this->runCommand(
            [PHP_BINARY, $this->repoRoot . '/scripts/stellar/render-runtime-env.php', '--output=' . $out1],
            $envWithNewline
        );
        $this->assertNotSame(0, $res1['code']);
        $this->assertFileDoesNotExist($out1);

        $envMissing = $this->baseRuntimeEnv();
        unset($envMissing['MAIL_PASS']);
        $out2 = $this->tmpDir . '/missing.env';
        $res2 = $this->runCommand(
            [PHP_BINARY, $this->repoRoot . '/scripts/stellar/render-runtime-env.php', '--output=' . $out2],
            $envMissing
        );
        $this->assertNotSame(0, $res2['code']);
        $this->assertFileDoesNotExist($out2);
        $this->assertStringContainsString('MAIL_PASS', $res2['stderr']);
    }

    public function testVerifyHttpRejectsInvalidUrlsAndMalformedBasicAuthWithoutLeakingSecret(): void
    {
        $badUrl = $this->runCommand([
            PHP_BINARY,
            $this->repoRoot . '/scripts/stellar/verify-http.php',
            '--public-url=ftp://demo.aeesgs.org',
            '--staff-url=https://bo.aeesgs.org',
        ]);
        $this->assertSame(2, $badUrl['code']);

        $badAuth = $this->runCommand(
            [
                PHP_BINARY,
                $this->repoRoot . '/scripts/stellar/verify-http.php',
                '--public-url=https://demo.aeesgs.org',
                '--staff-url=https://bo.aeesgs.org',
                '--basic-auth-env=STELLAR_STAGING_BASIC_AUTH',
            ],
            ['STELLAR_STAGING_BASIC_AUTH' => 'missing-colon-secret-token']
        );
        $this->assertSame(2, $badAuth['code']);
        $this->assertStringNotContainsString('missing-colon-secret-token', $badAuth['stdout']);
        $this->assertStringNotContainsString('missing-colon-secret-token', $badAuth['stderr']);

        // Passing --basic-auth-env with an unset or empty variable must fail closed.
        $missingAuth = $this->runCommand([
            PHP_BINARY,
            $this->repoRoot . '/scripts/stellar/verify-http.php',
            '--public-url=https://demo.aeesgs.org',
            '--staff-url=https://bo.aeesgs.org',
            '--basic-auth-env=STELLAR_STAGING_BASIC_AUTH',
        ]);
        $this->assertSame(2, $missingAuth['code']);
        $this->assertStringContainsString('STELLAR_STAGING_BASIC_AUTH', $missingAuth['stderr']);
    }

    /**
     * @return array{binDir: string, logFile: string}
     */
    private function createSshStubs(): array
    {
        $binDir = $this->tmpDir . '/stub-bin';
        mkdir($binDir, 0700, true);
        $logFile = $this->tmpDir . '/ssh-calls.log';

        foreach (['ssh', 'scp'] as $tool) {
            $stdinLog = $this->tmpDir . '/ssh-stdin.log';
            $script = "#!/usr/bin/env bash\n"
                . "set -euo pipefail\n"
                . "printf '%s:' '{$tool}' >> \"{$logFile}\"\n"
                . "for a in \"\$@\"; do printf ' [%s]' \"\$a\" >> \"{$logFile}\"; done\n"
                . "printf '\\n' >> \"{$logFile}\"\n"
                . "if [[ '{$tool}' == 'ssh' ]]; then\n"
                . "  cat >> \"{$stdinLog}\" || true\n"
                . "fi\n";
            $path = $binDir . '/' . $tool;
            file_put_contents($path, $script);
            chmod($path, 0700);
        }

        return ['binDir' => $binDir, 'logFile' => $logFile, 'stdinLog' => $this->tmpDir . '/ssh-stdin.log'];
    }

    public function testPublishShDeployAndRollbackUsePinnedSshAndForwardStellarOptions(): void
    {
        $stubs = $this->createSshStubs();
        $archive = $this->tmpDir . '/release.tar.gz';
        $config = $this->tmpDir . '/runtime.env';
        file_put_contents($archive, 'synthetic-archive');
        file_put_contents($config, "APP_ENV=test\n");
        chmod($config, 0600);

        $commonEnv = [
            'PATH' => $stubs['binDir'] . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'STELLAR_SSH_HOST' => 'stellar.example.invalid',
            'STELLAR_SSH_PORT' => '21098',
            'STELLAR_SSH_USER' => 'aeesjwmz',
            'STELLAR_SSH_PRIVATE_KEY' => "-----BEGIN OPENSSH PRIVATE KEY-----\ns3cr3t-private-key-material\n-----END OPENSSH PRIVATE KEY-----",
            'STELLAR_SSH_KNOWN_HOSTS' => '[stellar.example.invalid]:21098 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey',
            'STELLAR_BASE_DIR' => '/home/aeesjwmz/amea-staging',
            'STELLAR_PUBLIC_DOCROOT' => '/home/aeesjwmz/demo.aeesgs.org',
            'STELLAR_STAFF_DOCROOT' => '/home/aeesjwmz/bo.aeesgs.org',
            'STELLAR_PHP_CLI' => '/opt/alt/php85/usr/bin/php',
            'STELLAR_PHP_INI_SCAN_DIR' => '/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85',
            'STELLAR_PHP_HANDLER' => '1',
            'STELLAR_STAGING_AUTH_FILE' => '/home/aeesjwmz/.htpasswds/demo.aeesgs.org/passwd',
        ];

        $deploy = $this->runCommand(
            [
                'bash',
                $this->repoRoot . '/scripts/stellar/publish.sh',
                '--mode=deploy',
                '--archive=' . $archive,
                '--config-source=' . $config,
                '--release-id=20261124-abcd1234',
            ],
            $commonEnv
        );

        $this->assertSame(0, $deploy['code'], $deploy['stderr']);
        $this->assertStringNotContainsString('s3cr3t-private-key-material', $deploy['stdout'] . $deploy['stderr']);

        $calls = (string) file_get_contents($stubs['logFile']);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', $calls);
        $this->assertStringContainsString('BatchMode=yes', $calls);
        $this->assertStringContainsString('IdentitiesOnly=yes', $calls);
        $this->assertStringContainsString('scp:', $calls);
        $this->assertStringContainsString('/home/aeesjwmz/amea-staging/tmp-deploy-20261124-abcd1234/release.tar.gz', $calls);
        $this->assertStringContainsString('/home/aeesjwmz/amea-staging/tmp-deploy-20261124-abcd1234/runtime.env', $calls);
        $this->assertStringContainsString('/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85', $calls);
        $this->assertStringContainsString('/home/aeesjwmz/.htpasswds/demo.aeesgs.org/passwd', $calls);

        // Now test rollback mode with empty optional vars (simulating OpenSSH remote shell evaluation).
        unlink($stubs['logFile']);
        $prodRollbackEnv = $commonEnv;
        unset($prodRollbackEnv['STELLAR_PHP_INI_SCAN_DIR'], $prodRollbackEnv['STELLAR_STAGING_AUTH_FILE']);
        $rollback = $this->runCommand(
            ['bash', $this->repoRoot . '/scripts/stellar/publish.sh', '--mode=rollback'],
            $prodRollbackEnv
        );
        $this->assertSame(0, $rollback['code'], $rollback['stderr']);
        $rbCalls = (string) file_get_contents($stubs['logFile']);
        $this->assertStringNotContainsString('scp:', $rbCalls);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', $rbCalls);
        $this->assertStringContainsString('/home/aeesjwmz/amea-staging', $rbCalls);
        $this->assertStringContainsString("''", $rbCalls, 'Empty optional SSH parameters must be shell-quoted so OpenSSH does not drop them');
    }

    public function testPublishShFailsClosedWithoutPinnedKnownHosts(): void
    {
        $stubs = $this->createSshStubs();
        $res = $this->runCommand(
            ['bash', $this->repoRoot . '/scripts/stellar/publish.sh', '--mode=rollback'],
            [
                'PATH' => $stubs['binDir'] . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
                'STELLAR_SSH_HOST' => 'stellar.example.invalid',
                'STELLAR_SSH_USER' => 'aeesjwmz',
                'STELLAR_SSH_PRIVATE_KEY' => 'dummy',
                'STELLAR_BASE_DIR' => '/home/aeesjwmz/amea-staging',
                'STELLAR_PUBLIC_DOCROOT' => '/home/aeesjwmz/demo.aeesgs.org',
                'STELLAR_STAFF_DOCROOT' => '/home/aeesjwmz/bo.aeesgs.org',
            ]
        );

        $this->assertNotSame(0, $res['code']);
        $this->assertFileDoesNotExist($stubs['logFile']);
    }

    public function testPublishShVerifyHttpRunsOverPinnedSshAndPassesBasicAuthViaStdinOnly(): void
    {
        $stubs = $this->createSshStubs();
        $baseEnv = [
            'PATH' => $stubs['binDir'] . ':' . (getenv('PATH') ?: '/usr/bin:/bin'),
            'STELLAR_SSH_HOST' => 'stellar.example.invalid',
            'STELLAR_SSH_PORT' => '21098',
            'STELLAR_SSH_USER' => 'aeesjwmz',
            'STELLAR_SSH_PRIVATE_KEY' => "-----BEGIN OPENSSH PRIVATE KEY-----\ns3cr3t-private-key-material\n-----END OPENSSH PRIVATE KEY-----",
            'STELLAR_SSH_KNOWN_HOSTS' => '[stellar.example.invalid]:21098 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey',
            'STELLAR_BASE_DIR' => '/home/aeesjwmz/amea-staging',
            'STELLAR_PUBLIC_DOCROOT' => '/home/aeesjwmz/demo.aeesgs.org',
            'STELLAR_STAFF_DOCROOT' => '/home/aeesjwmz/bo.aeesgs.org',
            'STELLAR_PHP_CLI' => '/opt/alt/php85/usr/bin/php',
            'STELLAR_PHP_INI_SCAN_DIR' => '/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85',
            'STELLAR_STAGING_BASIC_AUTH' => 'stageuser:s3cr3t-basic-auth-token',
        ];

        $staging = $this->runCommand(
            [
                'bash',
                $this->repoRoot . '/scripts/stellar/publish.sh',
                '--mode=verify-http',
                '--public-url=https://demo.aeesgs.org',
                '--staff-url=https://bo.aeesgs.org',
                '--basic-auth-env=STELLAR_STAGING_BASIC_AUTH',
            ],
            $baseEnv
        );

        $this->assertSame(0, $staging['code'], $staging['stderr']);
        $calls = (string) file_get_contents($stubs['logFile']);
        $stdin = (string) file_get_contents($stubs['stdinLog']);

        $this->assertStringNotContainsString('scp:', $calls);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', $calls);
        $this->assertStringContainsString('verify-http.php', $calls);
        $this->assertStringContainsString('https://demo.aeesgs.org', $calls);
        $this->assertStringContainsString('https://bo.aeesgs.org', $calls);
        $this->assertStringContainsString('--basic-auth-env=STELLAR_VERIFY_BASIC_AUTH', $calls);
        // Basic auth secret must NEVER appear in SSH argv or stdout/stderr; only on stdin.
        $this->assertStringNotContainsString('s3cr3t-basic-auth-token', $calls);
        $this->assertStringNotContainsString('s3cr3t-basic-auth-token', $staging['stdout'] . $staging['stderr']);
        $this->assertStringContainsString('stageuser:s3cr3t-basic-auth-token', $stdin);

        // Fails closed when --basic-auth-env is specified but unset.
        unlink($stubs['logFile']);
        $missingAuthEnv = $baseEnv;
        unset($missingAuthEnv['STELLAR_STAGING_BASIC_AUTH']);
        $missing = $this->runCommand(
            [
                'bash',
                $this->repoRoot . '/scripts/stellar/publish.sh',
                '--mode=verify-http',
                '--public-url=https://demo.aeesgs.org',
                '--staff-url=https://bo.aeesgs.org',
                '--basic-auth-env=STELLAR_STAGING_BASIC_AUTH',
            ],
            $missingAuthEnv
        );
        $this->assertNotSame(0, $missing['code']);
        $this->assertFileDoesNotExist($stubs['logFile']);

        // Production mode (no --basic-auth-env) succeeds without passing basic auth flags.
        $prod = $this->runCommand(
            [
                'bash',
                $this->repoRoot . '/scripts/stellar/publish.sh',
                '--mode=verify-http',
                '--public-url=https://app.aeesgs.org',
                '--staff-url=https://admin.aeesgs.org',
            ],
            $missingAuthEnv
        );
        $this->assertSame(0, $prod['code'], $prod['stderr']);
        $prodCalls = (string) file_get_contents($stubs['logFile']);
        $this->assertStringContainsString('verify-http.php', $prodCalls);
        $this->assertStringNotContainsString('--basic-auth-env=', $prodCalls);
    }

    public function testCiWorkflowPreservesRequiredChecksAndGatesStellarPublication(): void
    {
        $ciPath = $this->repoRoot . '/.github/workflows/ci.yml';
        $this->assertFileExists($ciPath);
        $workflow = (string) file_get_contents($ciPath);

        // Single workflow convention.
        $workflowFiles = glob($this->repoRoot . '/.github/workflows/*.y*ml') ?: [];
        $this->assertCount(1, $workflowFiles);

        // Preserve all 5 required CI job names verbatim.
        foreach ([
            'name: "Storage: deterministic S3, guarded cutover & recovery"',
            'name: "System tests: Docker & recovery"',
            'name: "Doctrine: MariaDB & Compose startup gates"',
            'name: "Regression tests: PHP, JS & HTTP"',
            'name: "Static checks: Syntax & access"',
        ] as $requiredName) {
            $this->assertStringContainsString($requiredName, $workflow);
        }

        // Main pushes and workflow_dispatch staging deploys must never be cancelled in-flight.
        $this->assertStringContainsString(
            "cancel-in-progress: \${{ github.ref != 'refs/heads/main' && !(github.event_name == 'workflow_dispatch' && inputs.stellar_target == 'staging') }}",
            $workflow
        );

        // Stellar local rehearsal and script syntax checks run in CI.
        $this->assertStringContainsString('python3 tests/http/stellar-split.py', $workflow);
        $this->assertStringContainsString('bash -n scripts/stellar/deploy.sh', $workflow);
        $this->assertStringContainsString('bash -n scripts/stellar/publish.sh', $workflow);

        // Staging and production Stellar publication jobs are properly gated and share scripts.
        $this->assertStringContainsString('deploy-stellar-staging:', $workflow);
        $this->assertStringContainsString("github.event_name == 'workflow_dispatch' && inputs.stellar_target == 'staging' && vars.STELLAR_STAGING_DEPLOY_ENABLED == 'true'", $workflow);
        $this->assertStringContainsString('environment: stellar-staging', $workflow);
        $this->assertStringContainsString('--app-env=staging', $workflow);

        $this->assertStringContainsString('deploy-stellar:', $workflow);
        $this->assertStringContainsString("github.event_name == 'push' && github.ref == 'refs/heads/main' && vars.STELLAR_DEPLOY_ENABLED == 'true'", $workflow);
        $this->assertStringContainsString('environment: production', $workflow);

        $this->assertStringContainsString('scripts/stellar/render-runtime-env.php', $workflow);
        $this->assertStringContainsString('scripts/stellar/publish.sh', $workflow);
        $this->assertStringContainsString('--mode=verify-http', $workflow);
    }
}
