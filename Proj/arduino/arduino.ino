

//

#include <lmic.h>
#include <hal/hal.h>
#include <SPI.h>
#include <TinyGPS.h>
#include <SoftwareSerial.h>
TinyGPS gps;
SoftwareSerial ss(3, 4); // Arduino TX, RX ->GPS

// LoRaWAN NwkSKey, network session key
// This is the default Semtech key, which is used by the prototype TTN
// network initially.
//ttn ----- MSB
static const PROGMEM u1_t NWKSKEY[16] = { 0x5A, 0x50, 0x89, 0xB1, 0x6B, 0xD8, 0x6E, 0xBF, 0x9D, 0xAE, 0x6D, 0xB9, 0xB4, 0x51, 0x90, 0x3A };
// LoRaWAN AppSKey, application session key
// This is the default Semtech key, which is used by the prototype TTN
// network initially.
//ttn ------ LSB
static const u1_t PROGMEM APPSKEY[16] = { 0x6C, 0xB1, 0x15, 0xC3, 0x37, 0xFC, 0xAA, 0x89, 0x93, 0x90, 0x3C, 0x4E, 0x33, 0x6A, 0x0B, 0xF1  };

//
// LoRaWAN end-device address (DevAddr)
// See http://thethingsnetwork.org/wiki/AddressSpace
// ttn
static const u4_t DEVADDR = 0x26011E55;



/* These callbacks are only used in over-the-air activation, so they are
  left empty here (we cannot leave them out completely unless
   DISABLE_JOIN is set in config.h, otherwise the linker will complain).*/
void os_getArtEui (u1_t* buf) { }
void os_getDevEui (u1_t* buf) { }
void os_getDevKey (u1_t* buf) { }


static osjob_t initjob, sendjob, blinkjob;

static void smartdelay(unsigned long ms);

unsigned int count = 0;

int ButtonStateGreen = 0;         // variable for reading the pushbutton status

int lastButtonStateGreen = 0;




const int buttonPinGreen = 12;     // the number of the pushbutton pin;
long millis_held;
long firstTime;
long lastAction=-30000;
const int buzzer = 5; //buzzer pin

String datastring1 = "";
String datastring2 = "";
String datastring3 = "";
uint8_t datasend[11];     //Used to store GPS data for uploading
uint8_t downlink[1];
char orient[3] = {"\0"};

char gps_lon[20] = {"\0"}; //Storage GPS info
char gps_lat[20] = {"\0"}; //Storage latitude
char function[1] = {'0'};
float flat, flon, falt;
int v=0;

/* Schedule TX every this many seconds (might become longer due to duty
  cycle limitations).*/
const unsigned TX_INTERVAL = 45;

// Pin mapping
const lmic_pinmap lmic_pins = {
  .nss = 10,
  .rxtx = LMIC_UNUSED_PIN,
  .rst = 9,
  .dio = {2, 6, 7},
};

void do_send(osjob_t* j) {
  // Check if there is not a current TX/RX job running
  if (LMIC.opmode & OP_TXRXPEND) {
    Serial.println("OP_TXRXPEND, not sending");
  } else {

    GPSRead();
    GPSWrite();
    if(function[0]=='0'){
      if(v==1){
        v=2;
      }else{
      lastAction=0;
      }
    }
    // Prepare upstream data transmission at the next possible time.
    LMIC_setTxData2(1, datasend, sizeof(datasend) - 1, 0);
    //LMIC_setTxData2(1, mydata, sizeof(mydata)-1, 0);
    Serial.println("Packet queued");
    Serial.print("LMIC.freq:");
    Serial.println(LMIC.freq);
    Serial.print("enviei com ");Serial.println(function[0]);
    Serial.println("");
    Serial.println("");
    Serial.println("Receive data:");

  }
}

void onEvent (ev_t ev) {
  Serial.print(os_getTime());
  Serial.print(": ");
  Serial.println(ev);
  switch (ev) {

    case EV_TXCOMPLETE:
      Serial.println("EV_TXCOMPLETE (includes waiting for RX windows)");
      if (LMIC.dataLen) {
        // data received in rx slot after tx
        Serial.print("Data Received: ");
        Serial.write(LMIC.frame + LMIC.dataBeg, LMIC.dataLen);
        memcpy(&downlink, LMIC.frame + LMIC.dataBeg,1);
        if(downlink[0] == '0'){
          tone(buzzer,500);
          delay(1000);
          noTone(buzzer);
          //Serial.println("recebi 0");
        }
        if(downlink[0] == '1'){
          tone(buzzer,500);
          delay(1000);
          noTone(buzzer);
          delay(500);
          tone(buzzer,500);
          delay(1000);
          noTone(buzzer);
          
          //Serial.println("recebi 1");
        }
        if(downlink[0] == '2'){
          tone(buzzer,1000);
          delay(1000);
          noTone(buzzer);
          //Serial.println("recebi 2");
        }
        if(downlink[0] == '3'){
          tone(buzzer,1000);
          delay(1000);
          noTone(buzzer);
          delay(500);
          tone(buzzer,1000);
          delay(1000);
          noTone(buzzer);
          //Serial.println("recebi 3");
        }

        Serial.println();

      }
      // Schedule next transmission
      function[0]='0';
      os_setTimedCallback(&sendjob, os_getTime() + sec2osticks(TX_INTERVAL), do_send);
      break;

    case EV_RXCOMPLETE:
      // data received in ping slot
      Serial.println("EV_RXCOMPLETE");
      break;
    default:
      Serial.println("Unknown event");
      break;
  }
}

void setup() {
  lastAction =-30000;
  // initialize digital pin  as an output.
  pinMode(buttonPinGreen, INPUT);
  // initialize the buzzer pin as output:
  pinMode(buzzer, OUTPUT);

  //function[0] = '0';
  Serial.begin(9600);
  ss.begin(9600);
  while (!Serial);
  Serial.println("LoRa GPS Example---- ");
  Serial.println("Connect to TTN");


  // LMIC init
  os_init();
  // Reset the MAC state. Session and pending data transfers will be discarded.
  LMIC_reset();
  LMIC_setClockError(MAX_CLOCK_ERROR * 1/100);
  
  /*    Set static session parameters. Instead of dynamically establishing a session
    by joining the network, precomputed session parameters are be provided.*/
#ifdef PROGMEM
  /* On AVR, these values are stored in flash and only copied to RAM
     once. Copy them to a temporary buffer here, LMIC_setSession will
     copy them into a buffer of its own again.*/
  uint8_t appskey[sizeof(APPSKEY)];
  uint8_t nwkskey[sizeof(NWKSKEY)];
  memcpy_P(appskey, APPSKEY, sizeof(APPSKEY));
  memcpy_P(nwkskey, NWKSKEY, sizeof(NWKSKEY));
  LMIC_setSession (0x1, DEVADDR, nwkskey, appskey);
#else
  // If not running an AVR with PROGMEM, just use the arrays directly
  LMIC_setSession (0x1, DEVADDR, NWKSKEY, APPSKEY);
#endif

  // Disable link check validation
  LMIC_setLinkCheckMode(0);

  // TTN uses SF9 for its RX2 window.
  LMIC.dn2Dr = DR_SF9;



  // Set data rate and transmit power (note: txpow seems to be ignored by the library)
  LMIC_setDrTxpow(DR_SF7, 14);

  // Start job
  //do_send(&sendjob);
}

void GPSRead()
{
  unsigned long age;
  gps.f_get_position(&flat, &flon, &age);

  flat == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flat, 6;
  flon == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flon, 6;//save six decimal places
  falt == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : falt, 2;//save two decimal places
  memset(&orient,(int)'\0',3);
  memcpy(&orient, TinyGPS::cardinal(gps.f_course()), 3);

}

void GPSWrite()
{
  /*Convert GPS data to format*/
  datastring1 += dtostrf(flat, 0, 4, gps_lat);
  datastring2 += dtostrf(flon, 0, 4, gps_lon);
  //datastring3 +=dtostrf(falt, 0, 2, gps_alt);

  int32_t lng = flat * 10000;
  int32_t lat = flon * 10000;

  datasend[0] = lat;
  datasend[1] = lat >> 8;
  datasend[2] = lat >> 16;

  datasend[3] = lng;
  datasend[4] = lng >> 8;
  datasend[5] = lng >> 16;

  datasend[6] = orient[0];
  datasend[7] = orient[1];
  datasend[8] = orient[2];

  datasend[9] = function[0];

  smartdelay(1000);
}

static void smartdelay(unsigned long ms)
{
  unsigned long start = millis();
  do
  {
    while (ss.available())
    {
      gps.encode(ss.read());
    }
  } while (millis() - start < ms);
}


void loop() {

  ButtonStateGreen = digitalRead(buttonPinGreen);

  if(v==0){
    Serial.println(gps.satellites());
    if(gps.satellites()!=TinyGPS::GPS_INVALID_SATELLITES){
      Serial.println("mudei");
      tone(buzzer,2000);
      delay(2000);
      noTone(buzzer);
      v=1;
      do_send(&sendjob);
    }
    smartdelay(1000);
  }

  if(v!=0){
    if(lastButtonStateGreen == LOW && ButtonStateGreen==HIGH && (millis()-lastAction >3000)){
      firstTime=millis();
      Serial.println(millis()-lastAction);
      Serial.println("");
    }
  
    if(lastButtonStateGreen == HIGH && ButtonStateGreen==LOW&& (millis()-lastAction >30000)){
      millis_held=millis()-firstTime;
     
  
      if(millis_held<3000){
        os_clearCallback(&sendjob); //clear scheduled job
        function[0] = '1';
        Serial.println("Botão Verde - tiro");
        os_setCallback(&sendjob, do_send);
        lastAction=millis();
        
      }else{
        os_clearCallback(&sendjob); //clear scheduled job
        function[0] = '2';
        Serial.println("Botão Verde - mina");
        os_setCallback(&sendjob, do_send);
        lastAction=millis();
      }
    }

    if(lastButtonStateGreen == LOW && ButtonStateGreen==HIGH && (millis()-lastAction <30000)){
      tone(buzzer,1000);
      delay(100);
      noTone(buzzer);
    }
      
    lastButtonStateGreen = ButtonStateGreen;
  
    os_runloop_once();
  }
}
