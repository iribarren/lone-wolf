@campaigns
Feature: Guided solo play
  As a solo player
  I start a campaign on an active system's opening stage and follow its flow
  so that every session resumes exactly where my story stopped
  (US2 quickstart V3 — FR-012/013/014/015/016/017/018)

  Background:
    Given a system named like "Guided Quest" exists with stages "Scene,Sequel" starting at "Scene" where "Scene" leads to "Sequel"

  Scenario: Quickstart V3 — create, refuse illegal move over HTTP, journal, resume
    Given I am a registered player
    When I start a campaign on the system named like "Guided Quest"
    Then my campaign opens at stage "Scene"
    When I try to advance over HTTP to stage "Epilogue"
    Then the refusal carries status 422
    And the refusal names the legal alternatives "Sequel"
    When I advance to stage "Sequel"
    Then my campaign sits at stage "Sequel"
    When I append the narrative "The wolf finally reaches the pass."
    Then my journal records 1 entry stamped at stage "Sequel" containing "The wolf finally reaches the pass."
    When I re-open my campaign by id
    Then my campaign resumes at stage "Sequel"

  Scenario: Journal entries keep their original stage stamp after advancing
    Given I am a registered player
    And I started a campaign on the system named like "Guided Quest"
    When I append the narrative "Cold wind over the ridge."
    And I advance to stage "Sequel"
    And I append the narrative "The sequel begins."
    Then my journal records 2 entries: 1 at stage "Scene" containing "Cold wind over the ridge." and 1 at stage "Sequel" containing "The sequel begins."
