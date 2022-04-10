<div>
<img src="docs/images/logo.png" height="100">
</div>

# Member Photo Feed

This frontend module for [Contao CMS](https://contao.org) allows to logged in Contao frontend
  users to upload their favorite images via a frontend module.
  The images then can be displayed with the **"gallery"** content element from the Contao core.
  You can use *Sort by: Filename (descending)* to show the latest images first.

![Screenshot Upload Plugin](docs/images/screenshot.png)

## Configuration
```yaml
# config/config.yml:
markocupic_member_picture_feed:
  # Add your valid file extensions
  valid_extensions: 'jpeg,jpg'
```

## Dependencies
- Contao > 4.12
- PHP 7.4 and higher
- All the JS scripts are written in pure Javasript (No jQuery dependency)
- Bootstrap 5 with its modal component
