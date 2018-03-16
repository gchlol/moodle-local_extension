@local @local_extension @javascript
Feature: Local extension user preferences
  In order to adjust personal preferences
  As a user
  I want to use a page or interface where I can set my preferences

  Background:
    Given the extension manager is configured         # local_extension
    And I am a teacher                                # local_extension

  Scenario: Navigate to preferences page
    When I follow "Extension Status"
    And I press "Preferences"
    Then I should see "Activity extensions preferences"

  Scenario: Set mail digest preference
    Given I am at the extension preferences page          # local_extension
    When I select the checkbox "Mail digest"              # local_extension
    And I press "Save changes"
    Then I should see "Preferences saved"
    And I go to the extension preferences page again      # local_extension
    And the checkbox "Mail digest" should be selected     # local_extension
