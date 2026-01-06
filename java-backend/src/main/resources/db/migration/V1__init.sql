create table sensors (
  id bigserial primary key,
  node_id varchar(64),
  name varchar(100) not null,
  type varchar(40) not null,
  unit varchar(16) not null,
  created_at timestamp not null default now()
);

create table sensor_readings (
  id bigserial primary key,
  sensor_id bigint not null references sensors(id) on delete cascade,
  reading_value double precision not null,
  created_at timestamp not null default now()
);
create index idx_sensor_time on sensor_readings(sensor_id, created_at);

create table aggregated_readings (
  id bigserial primary key,
  sensor_id bigint not null references sensors(id) on delete cascade,
  bucket_start timestamp not null,
  period varchar(10) not null,
  avg double precision,
  min double precision,
  max double precision,
  cnt int,
  unique(sensor_id, bucket_start, period)
);

create table api_tokens (
  id bigserial primary key,
  token varchar(120) not null unique,
  name varchar(100),
  active boolean not null default true,
  created_at timestamp not null default now()
);
