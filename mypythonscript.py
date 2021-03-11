#!/usr/bin python

from Crypto.PublicKey import RSA
from Crypto.Random import get_random_bytes
from Crypto.Cipher import AES, PKCS1_OAEP
import base64
import sys

def bs64(vl):
  return str(base64.b64encode(vl), 'utf-8')

def enc_data(dat):
  plaintext = dat
  plaintext = str.encode(plaintext)
  key = b'al\x83j\xd7M\xca*3\x11\xc4\x8e\x7f\xf0\xbf\x1a' #get_random_bytes(16)
  cipher = AES.new(key, AES.MODE_EAX)
  msg = {"nounce":bs64(cipher.nonce),"cipher":bs64(cipher.encrypt(plaintext))}
  return msg

def dec_data(data):
  dd = data    #.split(',')
  print(dd)
  nonce = base64.b64decode(dd[0])
  ciphertext = base64.b64decode(dd[1])
  mac = base64.b64decode(dd[2])
  key = b'al\x83j\xd7M\xca*3\x11\xc4\x8e\x7f\xf0\xbf\x1a'
  #cipher = AES.new(key, AES.MODE_CCM, nonce)
  cipher = AES.new(key, AES.MODE_EAX, nonce)
  plaintext = cipher.decrypt(ciphertext)
  print(plaintext)
  plaintext = plaintext.decode("utf-8")
  plaintext = plaintext.split("~")
  return plaintext
# print(dec_data(enc_data('abc')))


if __name__ == "__main__":
  print(enc_data(sys.argv[1]))
