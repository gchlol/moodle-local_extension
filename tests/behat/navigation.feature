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

  Scenario: Prevent access to the extensions page if it is not configured
    Given I am logged in as student                   # local_extension
    When I am on site homepage
    Then I should not see "Extension Status"

  Scenario: A student of a course can view a direct link to request extensions
    Given the extension manager is configured                       # local_extension
    And the user "maverick" is enrolled into "topgun" as "student"  # local_extension
    And the course "topgun" has an assignment "Avionics"            # local_extension
    And I am logged in as maverick                                  # local_extension
    When I am on course "topgun" page                               # local_extension
    And I follow "Request Extension"
    Then I should see "New Extension Request"
    And I should see "Avionics"

  Scenario: A user not enrolled in a course cannot view a direct link to request extensions
    Given the extension manager is configured                       # local_extension
    And the user "maverick" is enrolled into "topgun" as "student"  # local_extension
    But I am logged in as administrator                             # local_extension
    When I am on course "topgun" page                               # local_extension
    Then I should not see "Request Extension"

  Scenario: If there are requests for a module, it should link it displaying its status
    Given the extension manager is configured                                 # local_extension
    And the user "maverick" is enrolled into "topgun" as "student"            # local_extension
    And "maverick" has an extension request for the "Avionics" assignment     # local_extension
    And I am logged in as maverick                                            # local_extension
    When I am on "Avionics" assignment page                                   # local_extension
    And I follow "Pending Extension"
    Then I should see "Avionics"
    And I should see "Pending Extension"
    And I should see "Extension History"
