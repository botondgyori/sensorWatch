# SensorWatch
# Build the images
docker compose build

# Start the containers
docker compose up -d

# Run database migrations
curl --location --request POST 'http://localhost:8080/index.php/api/migrate'

# 1. Run seeder
curl --location --request POST 'http://localhost:8080/index.php/api/seed/run'

# 2. Run reading processor 
curl --location --request POST 'http://localhost:8080/index.php/api/admin/process_readings'

# 3. Show S1 still `normal` (no notification)
curl --location 'http://localhost:8080/index.php/api/sensors/1/state' \
--header 'X-Api-Key: botond-key-001'

# 4. Show S2: Sensor alert with notification
curl --location 'http://localhost:8080/index.php/api/notifications?userId=1' \
--header 'X-Api-Key: botond-key-001'

# 5. Show S4: Clear notification after clear dwell
curl --location 'http://localhost:8080/index.php/api/sensors/2/state' \
--header 'X-Api-Key: botond-key-001'

# 6. Show S5: Alert state with NO duplicate notification (cooldown suppression)
curl --location 'http://localhost:8080/index.php/api/sensors/2/state' \
--header 'X-Api-Key: botond-key-001'

# 7. Show S6: Boolean door sensor behavior
curl --location 'http://localhost:8080/index.php/api/sensors/5/state' \
--header 'X-Api-Key: botond-key-001'

# 8. Attempt to read another tenant’s sensor/notifications — denied
    Attempt to read another tenant's sensor:
        curl --location 'http://localhost:8080/index.php/api/sensors/6/state' \
        --header 'X-Api-Key: botond-key-001'

    Attempt to read another tenant's notifications:
        curl --location 'http://localhost:8080/index.php/api/notifications?userId=1' \
        --header 'X-Api-Key: csongor-key-002'

# 9. Run Processor a second time — no duplicate notifications
curl --location --request POST 'http://localhost:8080/index.php/api/admin/process_readings'