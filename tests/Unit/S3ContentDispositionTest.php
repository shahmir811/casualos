<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * s3_content_disposition() feeds ResponseContentDisposition on presigned S3
 * URLs (catalogue book view + HD gallery download). S3 rejects any value that
 * isn't ISO-8859-1, so every result must be pure ASCII.
 */
class S3ContentDispositionTest extends TestCase
{
    private function assertAscii(string $value): void
    {
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $value, "Not pure ASCII: {$value}");
    }

    public function test_emoji_filename_from_production_is_ascii_safe(): void
    {
        $name = 'Khanam …. THE DERA KHAISTAH DAH 👌❤️.pdf';

        $value = s3_content_disposition('inline', $name, 'catalogue-book.pdf');

        $this->assertAscii($value);
        $this->assertStringStartsWith('inline; filename="', $value);
        $this->assertStringContainsString("filename*=utf-8''", $value);
        $this->assertStringContainsString('KHAISTAH', $value);
        // The encoded part round-trips to the original name.
        preg_match("/filename\*=utf-8''(.+)$/", $value, $m);
        $this->assertSame($name, rawurldecode($m[1]));
    }

    public function test_urdu_filename_is_transliterated_and_ascii_safe(): void
    {
        $value = s3_content_disposition('inline', 'کیٹلاگ.pdf', 'catalogue-book.pdf');

        $this->assertAscii($value);
        $this->assertStringContainsString("filename*=utf-8''", $value);
    }

    public function test_emoji_only_filename_falls_back_to_default(): void
    {
        $value = s3_content_disposition('inline', '👌❤️.pdf', 'catalogue-book.pdf');

        $this->assertAscii($value);
        $this->assertStringStartsWith('inline; filename=catalogue-book.pdf;', $value);
        $this->assertStringContainsString("filename*=utf-8''", $value);
    }

    public function test_plain_filename_is_unchanged(): void
    {
        $value = s3_content_disposition('attachment', 'Sakoon Lookbook.pdf', 'catalogue-book.pdf');

        $this->assertAscii($value);
        $this->assertSame('attachment; filename="Sakoon Lookbook.pdf"', $value);
    }

    public function test_null_and_unsafe_characters_are_handled(): void
    {
        $this->assertSame(
            'inline; filename=catalogue-book.pdf',
            s3_content_disposition('inline', null, 'catalogue-book.pdf')
        );

        $value = s3_content_disposition('attachment', 'a/b\\c "100%".jpg', 'hd-image.jpg');
        $this->assertAscii($value);
        $this->assertStringNotContainsString('/', explode(';', $value, 2)[1]);
    }
}
