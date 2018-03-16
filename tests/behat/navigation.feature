@local @local_extension @javascript
Feature: Smart navigation items
  In order to view or request extensions
  As a user
  I want to use a link to the extension plugin according to the page (context) where I am

  Scenario: Users should access the extensions page
    Given the extension manager is configured         # local_extension
    And I am logged in as student                     # local_extension
    When I am on site homepage
    And I follow "Extension Status"
    Then I should see "Extension status list"

  Scenario: Must be logged in to access the extensions page
    Given the extension manager is configured         # local_extension
    When I am on site homepage
    Then I should not see "Extension Status"

  Scenario: Guests cannot in to access the extensions page
    Given the extension manager is configured         # local_extension
    And I am logged in as guest                       # local_extension
    When I am on site homepage
    Then I should not see "Extension Status"
