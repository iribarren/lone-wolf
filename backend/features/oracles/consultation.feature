@oracles
Feature: Consult oracles during play
  As a solo player
  I consult only the oracle tables that apply to my campaign, receive
  exactly one weighted-random result per consultation, and can save it
  into my journal so uncertainty becomes recorded story
  (US4 quickstart V4 — FR-009/010/011)

  Background:
    Given a game system named like "Oracle Home" exists
    And a game system named like "Oracle Away" exists

  Scenario: Browsing shows global tables plus own-system tables only (scenario 1)
    Given I am playing on the system named like "Oracle Home"
    And a global oracle "Weather" with entries "Clear skies.|3, Storm rolling in.|1"
    And an oracle "Away Encounters" scoped to the system named like "Oracle Away" with entries "Ambush.|2, Quiet trail.|1"
    When I list my campaign's oracles over HTTP
    Then the listing contains "Weather"
    And the listing does not contain "Away Encounters"

  Scenario: A consultation answers exactly one weighted result (scenario 2)
    Given I am playing on the system named like "Oracle Home"
    And a global oracle "Weather" with entries "Clear skies.|3, Storm rolling in.|1"
    When I consult the oracle "Weather" over HTTP
    Then the consultation answers status "selected"
    And the consultation carries exactly one entry of weight-consistent shape

  Scenario: Saving the consulted result journals title and text (scenario 3)
    Given I am playing on the system named like "Oracle Home"
    And a global oracle "Weather" with entries "Cold rain sets in.|1"
    When I consult the oracle "Weather" over HTTP
    Then the consultation answers status "selected"
    When I save that consultation to my journal
    Then my journal records an oracle_result entry containing "Cold rain sets in."
