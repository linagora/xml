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

    public function testNextStillReturnsFalseAtTheEndOfTheDocument()
    {
        $reader = new Reader();
        $reader->xml('<?xml version="1.0"?><root/>');
        $reader->read();

        $this->assertFalse($reader->next());
    }
}
