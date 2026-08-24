@dice
Feature: Roll dice with standard notation
  As a solo player
  I submit NdM±K rolls and see every individual die plus the modified
  total, pathological input is refused before any die is thrown with a
  specific reason, and I can log rolls into my journal so mechanical
  truth lives beside the narrative
  (US6 quickstart V6 — FR-026/027/028/029)

  Background:
    Given a dice game system named like "Dice Home" exists

  Scenario: One d20 plus five shows one die and a bounded total (V6 row 1)
    Given I am playing a campaign on the system named like "Dice Home"
    When I roll "1d20+5" over HTTP
    Then the roll shows exactly 1 die within 1 and 20
    And the roll total lies between 6 and 25

  Scenario: Two d6 show both dice and their plain sum (V6 row 2)
    Given I am playing a campaign on the system named like "Dice Home"
    When I roll "2d6" over HTTP
    Then the roll shows exactly 2 dice within 1 and 6
    And the roll total equals the shown dice summed with modifier 0

  Scenario Outline: Pathological input is refused pre-roll with a specific reason (V6 row 3)
    Given I am playing a campaign on the system named like "Dice Home"
    When I roll "<notation>" over HTTP
    Then the roll is refused with reason "<reason>"
    And no result is shown

    Examples:
      | notation | reason        |
      | 2d       | malformed     |
      | d20x     | malformed     |
      | 0d6      | invalid_count |
      | 1d0      | invalid_faces |

  Scenario: The log action appends the roll to the journal (V6 row 4)
    Given I am playing a campaign on the system named like "Dice Home"
    When I log the roll of "1d20+5" into my journal over HTTP
    Then my journal records a dice_roll entry for "1d20+5"
