<?php

declare(strict_types=1);

namespace phpSwarm\Tests;

use PHPUnit\Framework\TestCase;
use phpSwarm\SwarmTools;
use phpSwarm\SwarmUtils;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class SwarmToolsTest extends TestCase
{
    private SwarmTools $swarmTools;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swarmTools = new SwarmTools(new SwarmUtils());
        $this->tempDir = sys_get_temp_dir() . '/phpswarm_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory($dir) {
        if (is_dir($dir)) { 
            $objects = scandir($dir);
            foreach ($objects as $object) { 
                if ($object != "." && $object != "..") { 
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                        $this->removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        unlink($dir. DIRECTORY_SEPARATOR .$object); 
                } 
            }
            rmdir($dir); 
        } 
    }

    public function testListFiles()
    {
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'test content');

        $files = json_decode($this->swarmTools->listFiles($this->tempDir), true);
        
        $this->assertContains('test.txt', $files);
    }

    public function testReadFile()
    {
        $testContent = "Hello, World!";
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, $testContent);

        $content = $this->swarmTools->readFile($testFile);
        
        $this->assertEquals($testContent, $content);
    }

    public function testWriteFile()
    {
        $testContent = "Test content";
        $testFile = $this->tempDir . '/test.txt';

        $result = $this->swarmTools->writeFile($testFile, $testContent, true);
        $this->assertEquals("File written successfully.", $result);

        $content = file_get_contents($testFile);
        $this->assertEquals($testContent, $content);
    }

    public function testRetrieveDocumentFromURL()
    {
        $mockBody = 'Example Domain';
        $mock = new MockHandler([
            new Response(200, [], $mockBody)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $swarmTools = $this->getMockBuilder(SwarmTools::class)
            ->setConstructorArgs([new SwarmUtils()])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $swarmTools->method('getHttpClient')->willReturn($client);

        $content = $swarmTools->retrieveDocumentFromURL("https://example.com");
        
        $this->assertEquals($mockBody, $content);
    }

    public function testRetrieveDocumentFromURLWithSave()
    {
        $mockBody = 'Example Domain';
        $mock = new MockHandler([
            new Response(200, [], $mockBody)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $swarmTools = $this->getMockBuilder(SwarmTools::class)
            ->setConstructorArgs([new SwarmUtils()])
            ->onlyMethods(['getHttpClient'])
            ->getMock();

        $swarmTools->method('getHttpClient')->willReturn($client);

        $savePath = $this->tempDir;
        $result = $swarmTools->retrieveDocumentFromURL("https://example.com/test.txt", $savePath);
        
        $this->assertStringContainsString("File downloaded and saved successfully", $result);
        $this->assertFileExists($savePath . '/test.txt');
        $this->assertEquals($mockBody, file_get_contents($savePath . '/test.txt'));
    }
}
