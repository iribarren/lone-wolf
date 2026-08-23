@oracle
Feature: Oracle authoring — global vs scoped visibility
  As an admin
  I can create oracles scoped to a system or made globally available
  so that players see exactly their system's oracles plus all global ones (FR-009).

  Background:
    Given an authenticated admin
    And the database is clean of oracles

  Scenario: Admin creates a globally visible oracle
    When I create a global oracle named "Global Treasures" with entries
      '{"text": "Yes.", "weight": 3}, {"text": "No.", "weight": 1}'
    Then the oracle appears in the player-facing list for every system
    And the oracle is marked as globally visible

  Scenario: Admin creates a system-scoped oracle
    Given a system named "My System" exists
    When I create a system-scoped oracle named "My Scoped Table" with entries
      '{"text": "Ambush.", "weight": 4}'
      scoped to "My System"
    Then the oracle appears only in the player-facing list for "My System"
    And the oracle does NOT appear for any other system
    And the oracle is marked as system-scoped

  Scenario: Players see only their system's scoped oracles plus all global ones
    Given two systems named "System A" and "System B" exist
    And a global oracle named "Global Table" exists
    And a system-scoped oracle named "System A Table" scoped to "System A" exists
    When I am a player on "System A"
    Then I see "Global Table" and "System A Table" in my oracle list
    And I do NOT see "System A Table" when playing on "System B"