# Anar API Connection Test

This directory contains a standalone test file to check the connection to the Anar API.

## Files

- `anar_connection_test.php` - Main test file that can be run directly on public_html

## How to Use

1. **Copy the test file to your public_html directory:**
   ```bash
   cp anar_connection_test.php /path/to/your/public_html/
   ```

2. **Access the test via browser:**
   ```
   https://yourdomain.com/anar_connection_test.php
   ```

3. **Enter your Anar token** when prompted

4. **View the test results** which will show:
   - Token validation status
   - Categories API connection test
   - Raw API responses for debugging

## What the Test Does

The test mimics the `fetch_and_save_categories_from_api_to_db_ajax` method from the Category class and performs two main tests:

1. **Token Validation Test**: Tests the token against `https://api.anar360.com/wp/auth/validate`
2. **Categories API Test**: Tests fetching categories from `https://api.anar360.com/wp/categories`

## Features

- ✅ Beautiful Persian/Farsi UI with RTL support
- ✅ Manual token input form
- ✅ Comprehensive error handling
- ✅ Retry mechanism for failed requests
- ✅ Detailed test results with success/error indicators
- ✅ Raw API responses for debugging
- ✅ No WordPress dependencies (standalone)

## Test Results

The test will show:
- Token validation status
- Shop URL and subscription details (if available)
- Number of categories fetched
- Sample category names
- Raw JSON responses for debugging

## Troubleshooting

If the test fails:
1. Check that your token is correct
2. Verify your server can make outbound HTTPS requests
3. Check the raw responses for specific error messages
4. Ensure your domain is properly configured in your Anar account

## Security Note

This test file is for development/testing purposes only. Remove it from production servers after testing.
