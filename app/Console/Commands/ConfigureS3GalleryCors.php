<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

/**
 * One-time, per-environment provisioning command — not scheduled.
 *
 * Direct-to-S3 browser uploads (the HD Gallery's presigned PUT) trigger a CORS
 * preflight from the browser. Without a CORS policy on the bucket allowing PUT
 * from the app's origin, every upload fails client-side with a CORS error before
 * it even reaches S3. This must be run once against each environment's bucket
 * before the HD Gallery upload feature works — same category of one-time setup
 * as `php artisan webpush:vapid`.
 *
 * AllowedOrigins is left as '*' deliberately: the actual access control is the
 * presigned URL itself (short-lived, scoped to one exact object key), not the
 * browser's CORS check, so a wildcard origin does not widen real access.
 *
 * WARNING: putBucketCors() REPLACES the bucket's entire CORS configuration —
 * if the bucket already serves other origins/rules, back those up first.
 */
class ConfigureS3GalleryCors extends Command
{
    protected $signature = 's3:configure-gallery-cors';

    protected $description = 'Apply the CORS policy required for direct browser-to-S3 HD Gallery uploads (run once per environment).';

    public function handle(): int
    {
        $config = config('filesystems.disks.s3');

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => $config['region'],
            'credentials' => [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        $client->putBucketCors([
            'Bucket' => $config['bucket'],
            'CORSConfiguration' => [
                'CORSRules' => [
                    [
                        'AllowedMethods' => ['PUT', 'GET', 'HEAD'],
                        'AllowedOrigins' => ['*'],
                        'AllowedHeaders' => ['*'],
                        'MaxAgeSeconds'  => 3000,
                    ],
                ],
            ],
        ]);

        $this->info("CORS policy applied to bucket [{$config['bucket']}]. Direct browser uploads for the HD Gallery are now allowed.");

        return self::SUCCESS;
    }
}
