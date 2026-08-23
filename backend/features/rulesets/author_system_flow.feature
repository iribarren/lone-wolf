Feature: Admins author game systems with campaign flows
  As an admin
  I author game systems owning exactly one flow
  So that players can start campaigns on them (quickstart V1/V2)

  Background:
    Given an authenticated admin

  Scenario: Authored systems appear in the player-facing list
    When the admin authors a "Scene-Sequel" system with stages "Scene, Sequel" starting at "Scene"
    And the admin authors a "Act Ladder" system with stages "Act I, Act II, Act III" starting at "Act I"
    Then the player-facing systems list contains "Scene-Sequel" and "Act Ladder"

  Scenario: An occupied stage cannot be orphaned by a flow edit
    Given a system named "Occupied" with stages "Scene, Sequel" starting at "Scene"
    And the stage "Scene" of a system named like "Occupied" is occupied by a campaign
    When the admin tries to change the flow of a system named like "Occupied" to stages "Renamed, Sequel" starting at "Renamed"
    Then the edit is refused because the stage is still occupied
