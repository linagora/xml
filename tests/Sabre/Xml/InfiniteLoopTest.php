<?php

declare(strict_types=1);

namespace Sabre\Xml;

class InfiniteLoopTest extends \PHPUnit\Framework\TestCase
{
    /**
     * This particular xml body caused the parser to go into an infinite loop.
     * Need to know why.
     */
    public function testDeserialize()
    {
        $body = '<?xml version="1.0"?>
<d:propertyupdate xmlns:d="DAV:" xmlns:s="http://sabredav.org/NS/test">
  <d:set><d:prop></d:prop></d:set>
  <d:set><d:prop></d:prop></d:set>
</d:propertyupdate>';

        $reader = new Reader();
        $reader->elementMap = [
            '{DAV:}set' => 'Sabre\\Xml\\Element\\KeyValue',
        ];
        $reader->xml($body);

        $output = $reader->parse();

        $this->assertEquals([
            'name' => '{DAV:}propertyupdate',
            'value' => [
                [
                    'name' => '{DAV:}set',
                    'value' => [
                        '{DAV:}prop' => null,
                    ],
                    'attributes' => [],
                ],
                [
                    'name' => '{DAV:}set',
                    'value' => [
                        '{DAV:}prop' => null,
                    ],
                    'attributes' => [],
                ],
            ],
            'attributes' => [],
        ], $output);
    }

    /**
     * A mismatched end tag right after an empty element made XMLReader::next()
     * fail without moving the cursor, so keyValue() parsed the same element
     * until max_execution_time. The exact bytes matter: libxml only stalls at
     * this position, so the fixture must be kept as is.
     */
    public function testKeyValueDoesNotLoopWhenTheCursorCannotMove()
    {
        $reader = new Reader();
        $reader->elementMap = [
            '{DAV:}prop' => 'Sabre\\Xml\\Deserializer\\keyValue',
        ];
        $reader->xml(file_get_contents(__DIR__.'/fixtures/keyvalue-stuck-cursor.xml'));

        $start = microtime(true);
        try {
            $reader->parse();
            $this->fail('A malformed document must not parse');
        } catch (ParseException $e) {
            $this->assertInstanceOf(LibXMLException::class, $e);
        }
        $this->assertLessThan(2.0, microtime(true) - $start);
    }

    /**
     * After a fatal libxml error, XMLReader::read() keeps returning false and
     * leaves the cursor where it was. mixedContent() and functionCaller()
     * ignore that return value: they spun on a comment, a processing
     * instruction or a foreign element, and mixedContent() recursed on its
     * own element until PHP crashed. libxml only reports the error once it
     * reaches it, so the padding pushes it past the first parsed chunk.
     *
     * @dataProvider stuckCursorDocuments
     */
    public function testDeserializersDoNotLoopWhenReadCannotMove(string $element, string $body)
    {
        $reader = new Reader();
        $reader->elementMap = [
            '{urn:x}m' => 'Sabre\\Xml\\Deserializer\\mixedContent',
            '{urn:x}f' => function (Reader $reader) {
                return Deserializer\functionCaller($reader, function ($a = null) {
                    return $a;
                }, 'urn:x');
            },
        ];
        $pad = str_repeat('p', 20000);
        $reader->xml('<?xml version="1.0"?><root xmlns="urn:x" xmlns:o="urn:o">'
            .sprintf($body, $element, $pad, chr(ord($element) + 1)));

        $start = microtime(true);
        try {
            $reader->parse();
            $this->fail('A malformed document must not parse');
        } catch (ParseException $e) {
            $this->assertInstanceOf(LibXMLException::class, $e);
        }
        $this->assertLessThan(2.0, microtime(true) - $start);
    }

    public function stuckCursorDocuments(): array
    {
        $bodies = [
            'stuck on the mapped element' => '<%1$s>%2$s</%3$s></root>',
            'stuck on a comment' => '<%1$s><!--%2$s--></%3$s></root>',
            'stuck on a processing instruction' => '<%1$s><?pi %2$s?></%3$s></root>',
            'stuck on a foreign element' => '<%1$s><o:z>%2$s</o:z></%3$s></root>',
            'stuck on a comment after text' => '<%1$s>text<!--%2$s--></%3$s></root>',
        ];
        $cases = [];
        foreach (['mixedContent' => 'm', 'functionCaller' => 'f'] as $function => $element) {
            foreach ($bodies as $label => $body) {
                $cases["$function, $label"] = [$element, $body];
            }
        }

        return $cases;
    }

    public function testReadStillReturnsFalseAtTheEndOfTheDocument()
    {
        $reader = new Reader();
        $reader->xml('<?xml version="1.0"?><root/>');
        $this->assertTrue($reader->read());

        $this->assertFalse($reader->read());
    }

    public function testNextStillReturnsFalseAtTheEndOfTheDocument()
    {
        $reader = new Reader();
        $reader->xml('<?xml version="1.0"?><root/>');
        $reader->read();

        $this->assertFalse($reader->next());
    }
}
