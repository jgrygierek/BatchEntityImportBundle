# Updating entities

If you want to update your entities:
- Set `allowOverrideEntity` to `true` in your import configuration file. 
- Then in your import file:
  - Add `entity_id` in header and:
    - Add entity ID to row
    - Leave it empty (if you want to set it manually or import it as new record)
  - Or if you don't want to add `entity_id` header, you can still manually set each entity to override.

## CSV file

```csv
entity_id,user_name
2,user_1
,user_2
10,user_3
```

[Back to README](../README.md)
