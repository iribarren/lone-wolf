@characters
Feature: Track characters with system-shaped sheets
  As a solo player
  I keep PCs and NPCs whose attributes conform to their system's sheet
  shape, with field-level guidance on mismatches and a lighter NPC set
  (US5 — FR-021..FR-024)

  Scenario: A conforming PC is accepted on its own system's shape (scenario 1)
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc, class:select:pc:Fighter|Mage"
    And I am running a campaign on the system named like "Sheet Home"
    When I create a/an "pc" named "Vex" with attributes '{"hp":14,"class":"Mage"}' over HTTP
    Then the character is accepted

  Scenario: Missing and wrong-typed fields are refused field-level (scenario 2)
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc, class:select:pc:Fighter|Mage"
    And I am running a campaign on the system named like "Sheet Home"
    When I create a/an "pc" named "Orrin" with attributes '{"hp":"twelve","class":"Bard"}' over HTTP
    Then the sheet refusal carries status 422
    And the refusal names violations for fields "hp,class"

  Scenario: The lighter NPC set passes where a PC would fail (scenario 3)
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc, class:select:pc:Fighter|Mage, bond:text:npc"
    And I am running a campaign on the system named like "Sheet Home"
    When I create a/an "npc" named "Mira" with attributes '{"bond":"Owes the wolf a debt."}' over HTTP
    Then the character is accepted

  Scenario: Unknown keys are refused outright
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc"
    And I am running a campaign on the system named like "Sheet Home"
    When I create a/an "pc" named "Ash" with attributes '{"hp":9,"spellSlots":4}' over HTTP
    Then the sheet refusal carries status 422
    And the refusal names violations for fields "spellSlots"

  Scenario: A character is re-saved through the edit endpoint (FR-023)
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc"
    And I am running a campaign on the system named like "Sheet Home"
    And I created a/an "pc" named "Vex" with attributes '{"hp":14}' over HTTP
    When I re-save that character as "Vela" with attributes '{"hp":12}' over HTTP
    Then the character is updated
    And that character is named "Vela"

  Scenario: A character's kind cannot change on an edit
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc"
    And I am running a campaign on the system named like "Sheet Home"
    And I created a/an "pc" named "Vex" with attributes '{"hp":14}' over HTTP
    When I re-save that character as a/an "npc" named "Vex" with attributes '{"hp":14}' over HTTP
    Then the sheet refusal carries status 422
    And the refusal names violations for fields "kind"

  Scenario: Re-saving a drifted character against the new shape clears its flag (FR-025)
    Given a game system named like "Sheet Home" exists with sheet "hp:number:pc"
    And I am running a campaign on the system named like "Sheet Home"
    And I created a/an "pc" named "Vex" with attributes '{"hp":14}' over HTTP
    And the system named like "Sheet Home" gains sheet "hp:number:pc, class:select:pc:Fighter|Mage"
    Then that character is flagged for review
    When I re-save that character as "Vex" with attributes '{"hp":14,"class":"Mage"}' over HTTP
    Then the character is updated
    And that character is clean
