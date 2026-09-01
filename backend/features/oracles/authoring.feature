@oracles
Feature: Author oracle tables in the backoffice
  As an admin
  I author oracle tables with their weighted result entries, scoped to a
  system or made globally available, so that players see exactly their
  system's oracles plus all global ones and every consultation has content
  to draw from (US3 — FR-007/FR-008/FR-009).

  Background:
    Given an authenticated backoffice admin

  Scenario: Admin authors a globally visible oracle with its entries
    When the admin authors a global oracle "Global Treasures" with entries "Yes.|3, No.|1"
    Then the oracle "Global Treasures" is stored with entries "Yes.|3, No.|1"
    And the oracle "Global Treasures" is marked as globally visible

  Scenario: Admin authors a system-scoped oracle with its entries
    Given a game system named like "My System" exists
    When the admin authors an oracle "My Scoped Table" scoped to the system named like "My System" with entries "Ambush.|4"
    Then the oracle "My Scoped Table" is stored with entries "Ambush.|4"
    And the oracle "My Scoped Table" is marked as scoped to the system named like "My System"

  Scenario: Authored tables reach players as global union own-system
    Given a game system named like "Reachable Home" exists
    And a game system named like "Reachable Away" exists
    And the admin authors a global oracle "Authored Weather" with entries "Clear skies.|3, Storm rolling in.|1"
    And the admin authors an oracle "Authored Away Encounters" scoped to the system named like "Reachable Away" with entries "Ambush.|2"
    When I am playing on the system named like "Reachable Home"
    And I list my campaign's oracles over HTTP
    Then the listing contains "Authored Weather"
    And the listing does not contain "Authored Away Encounters"
    When I consult the oracle "Authored Weather" over HTTP
    Then the consultation answers status "selected"
    And the consultation carries exactly one entry authored in the backoffice

  Scenario: A game system owns at most one scoped table
    Given a game system named like "Solo Sect" exists
    And the admin authors an oracle "First Scoped Table" scoped to the system named like "Solo Sect" with entries "Ambush.|2"
    When the admin authors an oracle "Second Scoped Table" scoped to the system named like "Solo Sect" with entries "Quiet trail.|1"
    Then the backoffice refuses because the system already owns a scoped table
    And exactly one oracle is scoped to the system named like "Solo Sect"
