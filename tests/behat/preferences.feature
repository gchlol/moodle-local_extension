@local @local_extension @javascript
Feature: Local extension user preferences
  In order to adjust personal preferences
  As a user
  I want to use a page or interface where I can set my preferences

  Scenario: Navigate to preferences page
    Given the extension manager is configured         # local_extension
    And I am an teacher                               # local_extension
    When I follow "Extension Status"
    And I press "Preferences"
    Then I should see "Activity extensions preferences"
