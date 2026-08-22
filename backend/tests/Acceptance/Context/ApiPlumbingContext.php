<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * HTTP-level API context driving the Symfony kernel through BrowserKit —
 * no live server, no external network (Constitution IV).
 *
 * Deliberately avoids PHPUnit\Framework\Assert: under Behat, PHPUnit's
 * assertion registry is not booted (PHPUnit 11+), so plain checks are used.
 */
final class ApiPlumbingContext implements Context
{
    private ?string $responseBody = null;

    private ?int $responseStatus = null;

    public function __construct(private readonly KernelBrowser $client)
    {
    }

    /**
     * @When I request :path
     */
    public function iRequest(string $path): void
    {
        $this->client->request('GET', $path);
        $response = $this->client->getResponse();

        $content = $response->getContent();

        if ($content === false) {
            throw new AssertionFailedError('The response body could not be read.');
        }

        $this->responseStatus = $response->getStatusCode();
        $this->responseBody = $content;
    }

    /**
     * @Then the response status code should be :expected
     */
    public function theResponseStatusCodeShouldBe(int $expected): void
    {
        if ($this->responseStatus !== $expected) {
            throw new AssertionFailedError(
                sprintf('Expected status %d but got %s.', $expected, var_export($this->responseStatus, true)),
            );
        }
    }

    /**
     * @Then the response body should contain :needle
     */
    public function theResponseBodyShouldContain(string $needle): void
    {
        if ($this->responseBody === null || !str_contains($this->responseBody, $needle)) {
            throw new AssertionFailedError(
                sprintf('Response body does not contain "%s".', $needle),
            );
        }
    }
}
