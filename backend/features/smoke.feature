Feature: Platform plumbing smoke
  As the delivery team
  I want the API skeleton to respond to requests
  So that every user story builds on verified plumbing

  Scenario: The OpenAPI contract document is published
    When I request "/api/docs.json"
    Then the response status code should be 200
    And the response body should contain "openapi"
