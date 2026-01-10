# Form Component

Complete form component with automatic email delivery to the configured admin.

**Security Note:** All forms POST to a secure internal endpoint (`/api/form`). The action URL is NOT configurable by users - this prevents malicious form redirects and ensures all submissions are properly validated and emailed to the admin.

## Basic Usage

### Simple Contact Form

```markdown
<Form
  name="contact"
  submit="Send Message"
  fields="
    text:name:Your Name:true:John Doe
    email:email:Email Address:true:you@example.com
    textarea:message:Message:true:Your message here
  "
/>
```

### Login/Registration Form

```markdown
<Form
  name="registration"
  submit="Create Account"
  success="Account created! Check your email for confirmation."
  redirect="/welcome"
  fields="
    text:firstName:First Name:true
    text:lastName:Last Name:true
    email:email:Email:true
    password:password:Password:true
    checkbox:terms:terms:true:I agree to the Terms of Service
  "
/>
```

### Survey Form

```markdown
<Form
  name="survey"
  submit="Submit Feedback"
  success="Thank you for your feedback!"
  fields="
    text:name:Name:true
    select:role:Your Role:true:::Developer,Designer,Manager,Other
    select:satisfaction:Satisfaction:true:::Very Satisfied,Satisfied,Neutral,Dissatisfied
    textarea:feedback:Comments:false:Share your thoughts...:8
  "
/>
```

## How It Works

1. **User fills out form** - Standard HTML form with validation
2. **Submits to `/api/form`** - Secure internal endpoint (not user-configurable)
3. **Security validation** - Token checked via Defense component
4. **Rate limiting** - 60 second cooldown between submissions
5. **Stored locally** - Saved to `site/submissions/` (viewable in admin panel)
6. **Emailed to admin** - Sent to address configured in `site/config.php`
7. **User feedback** - Shows success message or redirects

## Props

### Required

- `name` - Form identifier (used in email subject: "New contact submission")

### Optional

- `fields` - Field definitions (can be in props or content)
- `submit` - Submit button text (default: "Submit")
- `submit_class` - Button CSS classes (default: dark full-width button)
- `success` - Success message (default: "Thank you! Your submission has been received.")
- `redirect` - URL to redirect after success (optional)
- `class` - Form wrapper CSS classes (default: "space-y-4")

## Field Format

Fields are defined one per line using colon-separated values:

```
type:name:label:required:placeholder:rows:options
```

### Field Components

1. **type** (required) - Field type
2. **name** (required) - Field name for form submission
3. **label** (optional) - Display label
4. **required** (optional) - `true` or `false` (default: false)
5. **placeholder** (optional) - Placeholder text (or checkbox label)
6. **rows** (optional) - Textarea rows (default: 6)
7. **options** (optional) - Select options (comma-separated)

### Supported Field Types

**Text Inputs:**
- `text` - Plain text
- `email` - Email with validation
- `password` - Password field (hidden)
- `tel` - Phone number
- `url` - URL with validation
- `number` - Numeric input
- `date` - Date picker

**Multi-line:**
- `textarea` - Multi-line text

**Selection:**
- `select` - Dropdown menu (requires options)
- `checkbox` - Single checkbox

### Field Examples

```
# Text field with validation
text:username:Username:true:johndoe

# Email field (validated)
email:email:Email Address:true:you@example.com

# Phone number
tel:phone:Phone Number:false:555-1234

# Textarea with custom rows
textarea:bio:About You:false:Tell us about yourself:10

# Select dropdown
select:country:Country:true:::USA,Canada,UK,Australia

# Checkbox with label
checkbox:terms:terms:true:I agree to the terms and conditions

# Password field
password:password:Password:true

# Date input
date:birthday:Date of Birth:false
```

## Email Configuration

Forms automatically email submissions to the admin address configured in `site/config.php`:

```php
<?php

return [
    'mail' => [
        'admin_email' => 'admin@example.com',
        'smtp_host' => 'localhost',
    ],
    // ...
];
```

**Email includes:**
- Form name
- Submission timestamp
- Submitter's IP address
- Referrer URL
- All form field values (formatted)

**Email subject:** `[Your Site Name] New {form-name} submission`

## Security Features

✅ **Fixed Endpoint** - Forms always POST to `/api/form` (not user-configurable)
✅ **CSRF Protection** - Automatic token validation via Defense component
✅ **Rate Limiting** - 60 second cooldown between submissions
✅ **Honeypot Fields** - Defense component adds entropy tracking
✅ **IP Tracking** - All submissions logged with IP address
✅ **Token Validation** - Defense hooks validate form tokens
✅ **Session Management** - Prevents rapid-fire submissions

## Complete Examples

### Contact Form

```markdown
---
title: Contact Us
---

# Get In Touch

We'd love to hear from you! Fill out the form below.

<Form
  name="contact"
  submit="Send Message"
  success="Thanks! We'll get back to you within 24 hours."
  fields="
    text:name:Your Name:true
    email:email:Email Address:true
    tel:phone:Phone Number:false:555-1234
    select:subject:Subject:true:::General,Support,Sales,Partnership
    textarea:message:Your Message:true:Tell us how we can help...:8
  "
/>
```

### Job Application Form

```markdown
<Form
  name="job-application"
  submit="Apply Now"
  success="Application received! We'll review it and get back to you soon."
  class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg space-y-6"
>
text:firstName:First Name:true
text:lastName:Last Name:true
email:email:Email Address:true
tel:phone:Phone Number:true
url:portfolio:Portfolio URL:false:https://yoursite.com
select:position:Position:true:::Frontend Developer,Backend Developer,Designer,DevOps
select:experience:Years of Experience:true:::0-2,3-5,6-10,10+
textarea:coverLetter:Cover Letter:true:Tell us why you're a great fit...:10
checkbox:authorize:authorize:true:I authorize the company to contact me
</Form>
```

### Newsletter Signup

```markdown
<Form
  name="newsletter"
  submit="Subscribe"
  success="Subscribed! Check your email to confirm."
  redirect="/thank-you"
  class="flex gap-2 items-end"
  submit_class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
>
email:email:Email:true:you@example.com
</Form>
```

### Feedback Form with Custom Styling

```markdown
<Form
  name="feedback"
  submit="Send Feedback"
  success="Thank you for helping us improve!"
  class="grid grid-cols-2 gap-4 bg-gradient-to-br from-purple-50 to-blue-50 p-8 rounded-3xl"
  submit_class="col-span-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-8 py-4 rounded-xl font-bold hover:from-purple-700 hover:to-blue-700"
>
text:name:Name:true
email:email:Email:true
select:rating:Rating:true:::Excellent,Good,Average,Poor
textarea:comments:Comments:false::8
checkbox:followUp:followUp:false:Contact me about my feedback
</Form>
```

## Admin Panel

All form submissions are:
1. **Emailed** to the admin address in real-time
2. **Stored** in `site/submissions/` as JSON files
3. **Viewable** in the admin panel under "Submissions" tab
4. **Downloadable** for backup/export

Access submissions at: `https://yoursite.com/admin` → Submissions tab

## Response Handling

### Success Response

```json
{
  "success": true,
  "message": "Thank you! Your submission has been received."
}
```

### With Redirect

```json
{
  "success": true,
  "message": "Thank you!",
  "redirect": "/thank-you"
}
```

### Error Response

```json
{
  "success": false,
  "message": "Please wait before submitting again."
}
```

## JavaScript Integration

Add custom handling to the form:

```javascript
document.querySelector('form[name="contact"]').addEventListener('submit', async (e) => {
  e.preventDefault();

  const formData = new FormData(e.target);
  const response = await fetch('/api/form', {
    method: 'POST',
    body: formData
  });

  const result = await response.json();

  if (result.success) {
    if (result.redirect) {
      window.location.href = result.redirect;
    } else {
      alert(result.message);
    }
  } else {
    alert(result.message);
  }
});
```

## Why No Custom Action?

**Security:** Allowing users to specify form actions could enable:
- Malicious redirects
- Data exfiltration to external sites
- Bypassing security validation
- Phishing attacks

By fixing the endpoint internally:
- All submissions go through validation
- Admin always receives notifications
- Defense component can track patterns
- No risk of form hijacking

This is a security-first design decision.
