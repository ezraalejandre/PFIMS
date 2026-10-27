<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\PlainTextInput;
class PlainTextInputTest extends TestCase {
    public function test_rejects_markup_and_code_but_preserves_normal_business_punctuation(): void {
        foreach(['<script>alert(1)</script>','a > b','&lt;img src=x&gt;',"x' OR 1=1 --",'; DROP TABLE users;','javascript:alert(1)',"a\0b",'=HYPERLINK("evil")'] as $value)$this->assertTrue(PlainTextInput::invalid($value),$value);
        foreach(["O'Connor & Sons",'Phase 2 - Batangas','Unit #4, Block 2','+63 (917) 123-4567','test+alias@example.com','12.50','-1.25','Material / Labor','100% complete','Select Construction Co.'] as $value)$this->assertFalse(PlainTextInput::invalid($value),$value);
    }
}
