@local @local_extension @javascript
Feature: Smart navigation items
  In order to view or request extensions
  As a user
  I want to use a link to the extension plugin according to the page (context) where I am

  Background:
    Given the extension manager is configured         # local_extension

  Scenario: Users should access the activity extension page
    Given I am logged in as student                   # local_extension
    And I am on site homepage
    When I follow "Extension Status"
    Then I should see "Extension status list"
