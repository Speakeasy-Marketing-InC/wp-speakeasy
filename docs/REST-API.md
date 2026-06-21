# REST API Documentation

## Application Password Management

### Create Application Password

Programmatically create WordPress Application Passwords using the plugin API key.

**Endpoint:** `POST /wp-json/speakeasy/v1/application-passwords`

**Authentication:** Plugin API key via `X-Speakeasy-API-Key` header

#### Request

```http
POST /wp-json/speakeasy/v1/application-passwords HTTP/1.1
Host: yoursite.com
X-Speakeasy-API-Key: your_plugin_api_key_here
Content-Type: application/json

{
  "username": "admin",
  "name": "Speakeasy Backend Access"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description                                                |
|-----------|--------|----------|------------------------------------------------------------|
| username  | string | Yes      | WordPress username to create Application Password for      |
| name      | string | No       | Name for the Application Password (auto-generated if omitted) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "password": "abcd 1234 efgh 5678 ijkl 9012",
  "username": "admin",
  "user_id": 1,
  "name": "Speakeasy Backend Access"
}
```

#### Error Responses

**401 Unauthorized - Missing API Key**
```json
{
  "code": "missing_api_key",
  "message": "API key is required",
  "data": {
    "status": 401
  }
}
```

**401 Unauthorized - Invalid API Key**
```json
{
  "code": "invalid_api_key",
  "message": "Invalid API key",
  "data": {
    "status": 401
  }
}
```

**400 Bad Request - Missing Username**
```json
{
  "code": "missing_username",
  "message": "Username is required",
  "data": {
    "status": 400
  }
}
```

**404 Not Found - User Not Found**
```json
{
  "code": "user_not_found",
  "message": "User not found",
  "data": {
    "status": 404
  }
}
```

**403 Forbidden - Application Passwords Disabled**
```json
{
  "code": "app_passwords_disabled",
  "message": "Application Passwords are not available for this user",
  "data": {
    "status": 403
  }
}
```

**503 Service Unavailable**
```json
{
  "code": "app_passwords_unavailable",
  "message": "Application Passwords are not available on this site",
  "data": {
    "status": 503
  }
}
```

**500 Internal Server Error**
```json
{
  "code": "creation_failed",
  "message": "Failed to create Application Password",
  "data": {
    "status": 500
  }
}
```

#### Behavior

1. Validates API key matches the plugin's stored key
2. Checks if the specified user exists
3. Verifies Application Passwords are available for the user
4. **Revokes any existing Application Password with the same name**
5. Creates a new Application Password
6. Returns the password (shown only once - not stored or logged)
7. Logs the creation for audit purposes

#### Examples

**cURL**
```bash
curl -X POST https://example.com/wp-json/speakeasy/v1/application-passwords \
  -H "X-Speakeasy-API-Key: spk_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "name": "Speakeasy Backend Access"
  }'
```

**JavaScript (Node.js)**
```javascript
const response = await fetch('https://example.com/wp-json/speakeasy/v1/application-passwords', {
  method: 'POST',
  headers: {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    username: 'admin',
    name: 'Speakeasy Backend Access'
  })
});

const data = await response.json();

if (data.success) {
  console.log('Application Password:', data.password);
  console.log('Username:', data.username);
  console.log('User ID:', data.user_id);
}
```

**Python**
```python
import requests

url = 'https://example.com/wp-json/speakeasy/v1/application-passwords'
headers = {
    'X-Speakeasy-API-Key': 'spk_1234567890abcdef',
    'Content-Type': 'application/json'
}
payload = {
    'username': 'admin',
    'name': 'Speakeasy Backend Access'
}

response = requests.post(url, json=payload, headers=headers)
data = response.json()

if data.get('success'):
    print(f"Application Password: {data['password']}")
    print(f"Username: {data['username']}")
    print(f"User ID: {data['user_id']}")
```

**PHP**
```php
<?php
$url = 'https://example.com/wp-json/speakeasy/v1/application-passwords';
$api_key = 'spk_1234567890abcdef';

$response = wp_remote_post(
    $url,
    array(
        'body'    => wp_json_encode(
            array(
                'username' => 'admin',
                'name'     => 'Speakeasy Backend Access',
            )
        ),
        'headers' => array(
            'X-Speakeasy-API-Key' => $api_key,
            'Content-Type'        => 'application/json',
        ),
        'timeout' => 15,
    )
);

if ( ! is_wp_error( $response ) ) {
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $data['success'] ) {
        echo 'Application Password: ' . $data['password'] . "\n";
        echo 'Username: ' . $data['username'] . "\n";
        echo 'User ID: ' . $data['user_id'] . "\n";
    }
}
```

## Finding Your Plugin API Key

1. Log in to WordPress admin
2. Navigate to **Settings → WP Speakeasy**
3. Find the "Backend Registration" section
4. Click **"Show Full Key"** to reveal the complete API key
5. Copy the key for use in API requests

## Security Considerations

- **Keep API key secret**: The API key allows creating Application Passwords for any user
- **Use HTTPS**: Always use HTTPS to protect API key in transit
- **Audit logging**: All password creations are logged with username, timestamp, and IP address
- **One-time display**: The generated password is returned only once - store it securely
- **Automatic revocation**: Creating a password with an existing name revokes the old one
- **Rate limiting**: Consider implementing rate limiting on your firewall/proxy

## Using the Generated Application Password

Once you have an Application Password, use it to authenticate REST API requests:

```bash
# Use with WordPress REST API
curl -X GET "https://example.com/wp-json/wp/v2/posts" \
  -u "admin:abcd 1234 efgh 5678 ijkl 9012"
```

The format is: `username:application_password`

## Troubleshooting

### 401 Unauthorized

- Verify you're using the correct plugin API key
- Check the key hasn't been regenerated (this happens on plugin reinstall)
- Ensure the `X-Speakeasy-API-Key` header is being sent

### 403 Forbidden

- User may have Application Passwords disabled
- Check user capabilities
- Verify the user account is active

### 503 Service Unavailable

- Application Passwords may not be available globally
- Ensure WordPress version is 5.6 or higher
- Check if HTTPS is enabled
- Verify the Application Passwords Enabler module is active

### Password Not Working

- Application Passwords are different from login passwords
- Ensure you're using the exact password returned by the API (spaces included)
- Check if the password was revoked
- Verify you're using it for REST API authentication, not admin login
