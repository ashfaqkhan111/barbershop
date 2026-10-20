-- createdb pemon_barbershop && psql -d pemon_barbershop -f sql/schema.sql
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  no_wa VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'customer' CHECK (role IN ('customer','admin')),
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE TABLE services (
  id SERIAL PRIMARY KEY,
  nama VARCHAR(80) NOT NULL,
  deskripsi TEXT NOT NULL,
  durasi_menit INT NOT NULL,
  harga INT NOT NULL,
  ikon VARCHAR(8) NOT NULL DEFAULT '✂',
  populer BOOLEAN NOT NULL DEFAULT false
);
INSERT INTO services (nama,deskripsi,durasi_menit,harga,ikon,populer) VALUES
('Haircut','Potongan klasik & modern (scissor/fade) yang disesuaikan dengan bentuk kepala dan wajah Anda.',45,50000,'✂',false),
('Haircut + Beard','Penataan rambut lengkap, rapi-kan jenggot dengan razor, dan balm organik.',60,70000,'🧔',true),
('Haircut + Wash & Massage','Potong rambut, keramas, handuk hangat, dan pijat kepala yang menenangkan.',75,95000,'💆',false);
