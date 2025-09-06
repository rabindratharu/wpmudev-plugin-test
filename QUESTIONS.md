# Coding Task Questions #

# 1. Reducing npm Build File Size:
While executing npm’s build command, you will notice that the resulting zipped file is considerably large. Any suggestions on how to optimize and reduce its size?


# 2. Enhancing Google Auth Plugin
The plugin introduces a new admin menu named **Google Auth**, featuring fields for Client ID and Client Secret. To enhance this functionality:

1. Ensure the page is translatable.
2. Set the Client Secret field as a password input for enhanced security.
3. Add functionality to the save button, directing inputs to the `wp-json/wpmudev/v1/auth/auth-url` REST endpoint.
4. Implement notifications for successful storage or error responses.
5. Secure the existing endpoint.
6. Complete the endpoint's callback for storing inputs in the `wpmudev_plugin_test_settings` option.
7. Verify correct retrieval using the mentioned methods.


# 3. Google oAuth Return URL Setup
To implement Google’s oAuth, establish a return URL endpoint at `/wp-json/wpmudev/v1/auth/confirm`, providing functionality to:

1. Retrieve user email.
2. If the email exists and the user is not logged in, log in the user.
3. If the email doesn’t exist, create a new user with a generated password, and log them in. Redirect to the admin or home page accordingly.
4. Create a shortcode to display a personalized message if the user is logged in or a link for Google oAuth login if not.


# 4. Admin Menu for Posts Maintenance
Introduce a new admin menu page titled **Posts Maintenance** featuring a **Scan Posts** button. When clicked, this button should scan all public posts and pages (with customizable post type filters) and update the `wpmudev_test_last_scan` post_meta with the current timestamp. Ensure that operation will keep running if the user leaves that page. This operation should be repeated daily to ensure ongoing maintenance.


# 5. WP-CLI Command for Terminal
For system administrators' convenience, create a WP-CLI command to execute the **Scan Posts** action (which you created in Task #4 above) from the terminal. Include clear instructions for usage and customization.


# 6. Composer Package Version Control
Prevent conflicts associated with using common composer packages in WordPress. Implement measures to ensure compatibility and prevent version conflicts with other plugins or themes.


# 7. Unit Testing for Scan Posts
Prioritize software testing by initiating unit tests. Specifically, design a unit test to validate the 'Scan Posts' functionality, ensuring it runs without errors and effectively scans post content or any specified criteria.

**Please be sure to adhere to WPCS rules in your code for all tasks in this test. Following these rules for consistency and best practices is a priority and of crucial importance.**

We wish you good luck!
