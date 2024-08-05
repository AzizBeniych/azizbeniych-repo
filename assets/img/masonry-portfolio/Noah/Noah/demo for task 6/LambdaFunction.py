import json
import boto3
from boto3.dynamodb.conditions import Key

def lambda_handler(event, context):
    dynamodb = boto3.resource('dynamodb')
    table = dynamodb.Table('Subscriptions')

    try:
        body = json.loads(event['body'])
        user_email = body['userEmail']
        music_id = body['musicId']

        # Check if the subscription already exists
        response = table.query(
            KeyConditionExpression=Key('UserId').eq(user_email) & Key('MusicId').eq(music_id)
        )
        if response['Items']:
            return {
                'statusCode': 400,
                'body': json.dumps("You have already subscribed to this music.")
            }

        # Insert the new item
        table.put_item(
            Item={
                'UserId': user_email,
                'MusicId': music_id
            }
        )
        return {
            'statusCode': 200,
            'body': json.dumps("Subscription successful")
        }

    except Exception as e:
        print(e)
        return {
            'statusCode': 500,
            'body': json.dumps("Error subscribing to music: " + str(e))
        }
